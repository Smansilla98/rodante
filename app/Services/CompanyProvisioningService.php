<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\DocumentCounter;
use App\Models\MeasurementZone;
use App\Models\MovementReason;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitConfiguration;
use App\Models\UnitPosition;
use App\Models\UnitType;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyProvisioningService
{
    /**
     * @return array{company: Company, admin: User, plain_password: string}
     */
    public function provision(array $companyData, array $adminData, ?Company $catalogSource = null): array
    {
        return DB::transaction(function () use ($companyData, $adminData, $catalogSource) {
            $company = Company::query()->create([
                'name' => $companyData['name'],
                'slug' => $companyData['slug'],
                'tax_id' => $companyData['tax_id'] ?? null,
                'is_active' => true,
            ]);

            $plain = $adminData['password'] ?? Str::password(12);
            $admin = User::query()->create([
                'company_id' => $company->id,
                'name' => $adminData['name'],
                'username' => $adminData['username'],
                'email' => $adminData['email'] ?? null,
                'password' => $plain,
                'role' => UserRole::Administrador,
                'is_active' => true,
                'must_change_password' => true,
                'is_super_admin' => false,
            ]);

            TenantContext::for($company, function () use ($company, $catalogSource) {
                $this->copyCatalog($catalogSource ?? Company::demo(), $company);
                DocumentCounter::query()->firstOrCreate(
                    ['company_id' => $company->id, 'document' => 'purchase'],
                    ['value' => 0]
                );
                DocumentCounter::query()->firstOrCreate(
                    ['company_id' => $company->id, 'document' => 'work_order'],
                    ['value' => 0]
                );
                DocumentCounter::query()->firstOrCreate(
                    ['company_id' => $company->id, 'document' => 'inventory'],
                    ['value' => 0]
                );
            });

            app(AuditService::class)->log('company.provisioned', $company, null, [
                'slug' => $company->slug,
                'admin_username' => $admin->username,
            ]);

            return [
                'company' => $company,
                'admin' => $admin,
                'plain_password' => $plain,
            ];
        });
    }

    private function copyCatalog(Company $source, Company $target): void
    {
        $maps = [
            'unit_types' => [],
            'unit_configurations' => [],
            'tire_sizes' => [],
            'tire_brands' => [],
            'tire_models' => [],
        ];

        TenantContext::withoutTenant(function () use ($source, $target, &$maps) {
            foreach (UnitType::withoutTenant()->where('company_id', $source->id)->get() as $row) {
                $copy = $row->replicate(['company_id']);
                $copy->company_id = $target->id;
                $copy->save();
                $maps['unit_types'][$row->id] = $copy->id;
            }

            foreach (UnitConfiguration::withoutTenant()->where('company_id', $source->id)->get() as $row) {
                $copy = $row->replicate(['company_id']);
                $copy->company_id = $target->id;
                $copy->save();
                $maps['unit_configurations'][$row->id] = $copy->id;

                foreach (UnitPosition::withoutTenant()->where('unit_configuration_id', $row->id)->get() as $pos) {
                    $p = $pos->replicate(['company_id', 'unit_configuration_id']);
                    $p->company_id = $target->id;
                    $p->unit_configuration_id = $copy->id;
                    $p->save();
                }
            }

            foreach (MovementReason::withoutTenant()->where('company_id', $source->id)->get() as $row) {
                $copy = $row->replicate(['company_id']);
                $copy->company_id = $target->id;
                $copy->save();
            }

            foreach (TireSize::withoutTenant()->where('company_id', $source->id)->get() as $row) {
                $copy = $row->replicate(['company_id']);
                $copy->company_id = $target->id;
                $copy->save();
                $maps['tire_sizes'][$row->id] = $copy->id;

                foreach (MeasurementZone::withoutTenant()->where('tire_size_id', $row->id)->get() as $zone) {
                    $z = $zone->replicate(['company_id', 'tire_size_id']);
                    $z->company_id = $target->id;
                    $z->tire_size_id = $copy->id;
                    $z->save();
                }
            }

            foreach (TireBrand::withoutTenant()->where('company_id', $source->id)->get() as $row) {
                $copy = $row->replicate(['company_id']);
                $copy->company_id = $target->id;
                $copy->save();
                $maps['tire_brands'][$row->id] = $copy->id;
            }

            foreach (TireModel::withoutTenant()->where('company_id', $source->id)->get() as $row) {
                $copy = $row->replicate(['company_id', 'tire_brand_id']);
                $copy->company_id = $target->id;
                $copy->tire_brand_id = $maps['tire_brands'][$row->tire_brand_id] ?? $row->tire_brand_id;
                $copy->save();
                $maps['tire_models'][$row->id] = $copy->id;

                $sizeIds = DB::table('tire_model_sizes')
                    ->where('tire_model_id', $row->id)
                    ->pluck('tire_size_id');
                foreach ($sizeIds as $sizeId) {
                    $newSize = $maps['tire_sizes'][$sizeId] ?? null;
                    if (! $newSize) {
                        continue;
                    }
                    DB::table('tire_model_sizes')->insert([
                        'tire_model_id' => $copy->id,
                        'tire_size_id' => $newSize,
                        'company_id' => $target->id,
                    ]);
                }
            }
        });
    }
}
