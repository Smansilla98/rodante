<?php

namespace App\Http\Controllers;

use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Services\PurchaseService;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function tiresCsv(Request $request): StreamedResponse
    {
        $query = Tire::with('brand', 'model', 'size', 'currentLocation.unit');
        AccessScope::tires($query, $request->user());

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Numero', 'Marca', 'Modelo', 'Medida', 'Estado', 'Condicion', 'Km', 'Ubicacion'], ';');
            $query->orderBy('id')->chunkById(500, function ($tires) use ($out) {
                foreach ($tires as $tire) {
                    fputcsv($out, [
                        $tire->individual_number,
                        $tire->brand?->name,
                        $tire->model?->code,
                        $tire->size?->code,
                        $tire->status->label(),
                        $tire->condition->label(),
                        $tire->accumulated_km,
                        $tire->currentLocation?->unit?->plate ?: $tire->status->label(),
                    ], ';');
                }
            });
            fclose($out);
        }, 'cubiertas-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importPurchases(Request $request, PurchaseService $purchases)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'supplier_id' => 'required|exists:suppliers,id',
            'base_id' => 'required|exists:bases,id',
            'purchased_at' => 'required|date',
        ]);
        $path = $data['file']->getRealPath();
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return back()->withErrors(['file' => 'El archivo está vacío.']);
        }
        $delimiter = substr_count($raw, ';') >= substr_count($raw, ',') ? ';' : ',';
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, $delimiter);
        if (! $header) {
            fclose($handle);

            return back()->withErrors(['file' => 'El archivo está vacío.']);
        }
        $items = [];
        $line = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($row === [null] || collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }
            $brand = TireBrand::where('name', trim((string) ($row[0] ?? '')))->first();
            $model = TireModel::where('code', trim((string) ($row[1] ?? '')))->first();
            $size = TireSize::where('code', trim((string) ($row[2] ?? '')))->first();
            $qty = (int) ($row[3] ?? 0);
            if (! $brand || ! $model || ! $size || $qty < 1) {
                fclose($handle);

                return back()->withErrors(['file' => "Fila {$line}: marca, modelo, medida y cantidad son obligatorios y tienen que existir en el catálogo."])->withInput();
            }
            $items[] = [
                'tire_brand_id' => $brand->id,
                'tire_model_id' => $model->id,
                'tire_size_id' => $size->id,
                'quantity' => $qty,
                'first_number' => isset($row[4]) && $row[4] !== '' ? (int) $row[4] : null,
                'unit_cost' => isset($row[5]) && $row[5] !== '' ? (float) str_replace(',', '.', (string) $row[5]) : null,
            ];
        }
        fclose($handle);
        if ($items === []) {
            return back()->withErrors(['file' => 'No hay filas válidas.']);
        }
        if (! AccessScope::seesEverything($request->user()) && ! in_array((int) $data['base_id'], AccessScope::visibleBaseIds($request->user()), true)) {
            abort(404);
        }
        $purchase = $purchases->create([
            'supplier_id' => $data['supplier_id'],
            'base_id' => $data['base_id'],
            'purchased_at' => $data['purchased_at'],
            'notes' => 'Importación CSV',
            'items' => $items,
        ], $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', 'Borrador importado. Revisá y confirmá.');
    }
}
