<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\AuditLog;
use App\Models\FleetUnit;
use App\Models\OdometerReading;
use App\Models\Tire;
use App\Models\TireBrand;
use App\Models\TireIncident;
use App\Models\TireMeasurement;
use App\Models\TireModel;
use App\Models\TireSize;
use App\Models\UnitCoupling;
use App\Services\CsvExportService;
use App\Services\PurchaseService;
use App\Services\ReportService;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private CsvExportService $csv) {}

    public function tiresCsv(Request $request): StreamedResponse
    {
        $query = $this->tiresQuery($request);

        return $this->csv->download('cubiertas', [
            'Numero', 'DOT', 'Marca', 'Modelo', 'Medida', 'Estado', 'Condicion', 'Km', 'Ubicacion',
        ], function ($write) use ($query) {
            $query->orderBy('id')->chunkById(500, function ($tires) use ($write) {
                foreach ($tires as $tire) {
                    $write([
                        $tire->individual_number,
                        $tire->dot,
                        $tire->brand?->name,
                        $tire->model?->code,
                        $tire->size?->code,
                        $tire->status->label(),
                        $tire->condition->label(),
                        $tire->accumulated_km,
                        $tire->currentLocation?->unit?->plate ?: ($tire->currentLocation?->base?->name ?: $tire->status->label()),
                    ]);
                }
            });
        });
    }

    public function unitsCsv(Request $request): StreamedResponse
    {
        $query = FleetUnit::with('type', 'fleet', 'base', 'configuration');
        AccessScope::units($query, $request->user());
        $query
            ->when($request->fleet_id, fn ($q, $id) => $q->where('fleet_id', $id))
            ->when($request->base_id, fn ($q, $id) => $q->where('base_id', $id))
            ->when($request->q, fn ($q, $term) => $q->where('plate', 'like', "%{$term}%"));

        return $this->csv->download('unidades', [
            'Patente', 'Tipo', 'Flota', 'Base', 'Configuracion', 'Estado', 'Odometro',
        ], function ($write) use ($query) {
            $query->orderBy('plate')->chunk(500, function ($units) use ($write) {
                foreach ($units as $unit) {
                    $write([
                        $unit->plate,
                        $unit->type?->name,
                        $unit->fleet?->name,
                        $unit->base?->name,
                        $unit->configuration?->code,
                        $unit->status,
                        $unit->current_odometer,
                    ]);
                }
            });
        });
    }

    public function measurementsCsv(Request $request): StreamedResponse
    {
        $query = TireMeasurement::with(['tire.model', 'unit', 'readings.zone']);
        $query->whereHas('tire', fn ($q) => AccessScope::tires($q, $request->user()));
        $query
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($request->fleet_id, fn ($q, $id) => $q->whereHas('unit', fn ($u) => $u->where('fleet_id', $id)))
            ->when($request->base_id, fn ($q, $id) => $q->whereHas('unit', fn ($u) => $u->where('base_id', $id)))
            ->when($request->from, fn ($q, $d) => $q->whereDate('measured_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('measured_at', '<=', $d))
            ->when($request->boolean('alert'), fn ($q) => $q->where('raises_alert', true));

        return $this->csv->download('mediciones', [
            'Fecha', 'Cubierta', 'Unidad', 'Profundidades', 'Alerta',
        ], function ($write) use ($query) {
            $query->latest('measured_at')->chunk(500, function ($rows) use ($write) {
                foreach ($rows as $m) {
                    $write([
                        $m->measured_at?->format('Y-m-d H:i'),
                        $m->tire?->displayName(),
                        $m->unit?->plate,
                        $m->readings->map(fn ($r) => ($r->zone?->name ?? '?').': '.$r->millimeters)->implode(' | '),
                        $m->raises_alert ? 'Si' : 'No',
                    ]);
                }
            });
        });
    }

    public function incidentsCsv(Request $request): StreamedResponse
    {
        $query = TireIncident::with(['tire.model', 'unit']);
        $query->whereHas('tire', fn ($q) => AccessScope::tires($q, $request->user()));
        $query
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($request->from, fn ($q, $d) => $q->whereDate('occurred_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('occurred_at', '<=', $d));

        return $this->csv->download('incidencias', [
            'Fecha', 'Tipo', 'Cubierta', 'Unidad', 'Detalle',
        ], function ($write) use ($query) {
            $query->latest('occurred_at')->chunk(500, function ($rows) use ($write) {
                foreach ($rows as $incident) {
                    $write([
                        $incident->occurred_at?->format('Y-m-d H:i'),
                        $incident->type instanceof IncidentType ? $incident->type->label() : $incident->type,
                        $incident->tire?->displayName(),
                        $incident->unit?->plate,
                        $incident->description ?: $incident->notes,
                    ]);
                }
            });
        });
    }

    public function couplingsCsv(Request $request): StreamedResponse
    {
        $query = UnitCoupling::with(['tractor', 'trailer']);
        $unitIds = FleetUnit::query();
        AccessScope::units($unitIds, $request->user());
        $ids = $unitIds->pluck('id');
        $query->where(function ($q) use ($ids) {
            $q->whereIn('tractor_id', $ids)->orWhereIn('trailer_id', $ids);
        });
        $query
            ->when($request->tractor_id, fn ($q, $id) => $q->where('tractor_id', $id))
            ->when($request->trailer_id, fn ($q, $id) => $q->where('trailer_id', $id))
            ->when($request->status === 'open', fn ($q) => $q->whereNull('uncoupled_at'))
            ->when($request->status === 'closed', fn ($q) => $q->whereNotNull('uncoupled_at'));

        return $this->csv->download('enganches', [
            'Estado', 'Tractor', 'Acoplado', 'Desde', 'Hasta', 'Odometro inicio', 'Odometro fin',
        ], function ($write) use ($query) {
            $query->latest('coupled_at')->chunk(500, function ($rows) use ($write) {
                foreach ($rows as $c) {
                    $write([
                        $c->isOpen() ? 'Abierto' : 'Cerrado',
                        $c->tractor?->plate,
                        $c->trailer?->plate,
                        $c->coupled_at?->format('Y-m-d H:i'),
                        $c->uncoupled_at?->format('Y-m-d H:i'),
                        $c->tractor_odometer_start,
                        $c->tractor_odometer_end,
                    ]);
                }
            });
        });
    }

    public function odometersCsv(Request $request): StreamedResponse
    {
        $query = OdometerReading::with('unit');
        $units = FleetUnit::query();
        AccessScope::units($units, $request->user());
        $query->whereIn('unit_id', $units->select('id'));
        $query
            ->when($request->unit_id, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s));

        return $this->csv->download('odometros', [
            'Fecha', 'Unidad', 'Valor', 'Estado', 'Notas',
        ], function ($write) use ($query) {
            $query->latest('recorded_at')->chunk(500, function ($rows) use ($write) {
                foreach ($rows as $reading) {
                    $write([
                        $reading->recorded_at?->format('Y-m-d H:i'),
                        $reading->unit?->plate,
                        $reading->value,
                        $reading->status instanceof \BackedEnum ? $reading->status->value : (string) $reading->status,
                        $reading->notes,
                    ]);
                }
            });
        });
    }

    public function auditCsv(Request $request): StreamedResponse
    {
        $query = AuditLog::query()->with('user');
        AccessScope::auditLogs($query, $request->user());

        return $this->csv->download('auditoria', [
            'Fecha', 'Usuario', 'Accion', 'Resumen',
        ], function ($write) use ($query) {
            $query->latest('created_at')->limit(5000)->get()->each(function ($log) use ($write) {
                    $write([
                    $log->created_at?->format('Y-m-d H:i'),
                    $log->user?->name,
                    $log->actionLabel(),
                    $log->detail(),
                ]);
            });
        });
    }

    public function reportKilometersCsv(Request $request, ReportService $reports): StreamedResponse
    {
        $tires = $reports->kilometersByTire($request->user());

        return $this->csv->download('reporte-km', [
            'Cubierta', 'Km', 'Vidas', 'Recapados', 'Reparaciones',
        ], function ($write) use ($tires) {
            foreach ($tires as $tire) {
                $write([
                    $tire->displayName(),
                    $tire->accumulated_km,
                    $tire->lifecycles_count,
                    $tire->recaps_count,
                    $tire->repairs_count,
                ]);
            }
        });
    }

    public function reportConsumptionCsv(Request $request, ReportService $reports): StreamedResponse
    {
        $rows = $reports->consumptionByModel($request->user());

        return $this->csv->download('reporte-consumo', [
            'Marca', 'Modelo', 'Compradas', 'Instaladas', 'Stock', 'Baja', 'Km promedio',
        ], function ($write) use ($rows) {
            foreach ($rows as $row) {
                $write([
                    $row->brand,
                    $row->model,
                    $row->purchased,
                    $row->installed,
                    $row->stock,
                    $row->retired,
                    round((float) $row->avg_km),
                ]);
            }
        });
    }

    public function reportIncidentsCsv(Request $request, ReportService $reports): StreamedResponse
    {
        $rows = $reports->incidents($request->user());

        return $this->csv->download('reporte-incidencias', [
            'Tipo', 'Cantidad', 'Cubiertas',
        ], function ($write) use ($rows) {
            foreach ($rows as $row) {
                $type = $row->type;
                $label = $type instanceof IncidentType
                    ? $type->label()
                    : (IncidentType::tryFrom((string) $type)?->label() ?? (string) $type);
                $write([$label, $row->total, $row->tires]);
            }
        });
    }

    private function tiresQuery(Request $request)
    {
        $query = Tire::with(['brand', 'model', 'size', 'currentLocation.unit', 'currentLocation.base']);
        AccessScope::tires($query, $request->user());

        return $query
            ->when($request->brand_id, fn ($q, $id) => $q->where('tire_brand_id', $id))
            ->when($request->model_id, fn ($q, $id) => $q->where('tire_model_id', $id))
            ->when($request->size_id, fn ($q, $id) => $q->where('tire_size_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->condition, fn ($q, $condition) => $q->where('condition', $condition))
            ->when($request->boolean('stock_only'), fn ($q) => $q->where('status', 'STOCK'))
            ->when($request->q, function ($q, $term) {
                $digits = preg_replace('/\D+/', '', $term);
                $dot = Tire::normalizeDot($term);
                $q->where(function ($inner) use ($term, $digits, $dot) {
                    $inner->where('individual_number', 'like', "%{$term}%")
                        ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$term}%"));
                    if ($digits) {
                        $inner->orWhere('individual_number', $digits);
                    }
                    if ($dot) {
                        $inner->orWhere('dot', 'like', "%{$dot}%");
                    }
                });
            });
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
                'dot' => isset($row[6]) && $row[6] !== '' ? Tire::normalizeDot((string) $row[6]) : null,
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
