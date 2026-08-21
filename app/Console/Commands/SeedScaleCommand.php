<?php

namespace App\Console\Commands;

use App\Models\Base;
use App\Models\Company;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedScaleCommand extends Command
{
    protected $signature = 'rodante:seed-scale {count : Cantidad de neumáticos} {--company=1 : Empresa destino}';

    protected $description = 'Inserta neumáticos en stock por lotes para pruebas de escala';

    public function handle(): int
    {
        $count = max(1, min(100000, (int) $this->argument('count')));
        $company = Company::query()->find($this->option('company')) ?? Company::demo();
        $this->callSilent('db:seed', ['--class' => CatalogSeeder::class]);
        $base = Base::query()->where('company_id', $company->id)->first()
            ?? Base::query()->create([
                'company_id' => $company->id,
                'name' => 'Base benchmark',
                'code' => 'BENCH-'.$company->id,
                'is_active' => true,
            ]);
        $brand = TireBrand::query()->firstOrFail();
        $model = TireModel::query()->firstOrFail();
        $size = $model->sizes()->first() ?? TireSize::query()->firstOrFail();
        $next = ((int) DB::table('tires')->where('company_id', $company->id)->max('individual_number')) + 1;
        $created = 0;

        while ($created < $count) {
            $batchSize = min(1000, $count - $created);
            $now = now();
            $rows = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $rows[] = [
                    'company_id' => $company->id,
                    'public_token' => (string) Str::uuid(),
                    'individual_number' => $next + $created + $i,
                    'tire_brand_id' => $brand->id,
                    'tire_model_id' => $model->id,
                    'tire_size_id' => $size->id,
                    'status' => 'STOCK',
                    'condition' => 'NUEVA',
                    'accumulated_km' => 0,
                    'purchased_at' => $now->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::transaction(function () use ($rows, $base) {
                DB::table('tires')->insert($rows);
                $ids = DB::table('tires')
                    ->where('company_id', $base->company_id)
                    ->whereIn('individual_number', array_column($rows, 'individual_number'))
                    ->pluck('id');
                $now = now();
                DB::table('tire_current_locations')->insert($ids->map(fn ($id) => [
                    'tire_id' => $id,
                    'location_kind' => 'STOCK',
                    'base_id' => $base->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
            $created += $batchSize;
            $this->output->write("\rInsertados: {$created}/{$count}");
        }

        $this->newLine();
        $this->info("Escala creada para empresa {$company->id}.");

        return self::SUCCESS;
    }
}
