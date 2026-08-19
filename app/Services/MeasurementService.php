<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Exceptions\DomainException;
use App\Models\Tire;
use App\Models\TireMeasurement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MeasurementService
{
    public function __construct(
        private IncidentService $incidents,
        private AuditService $audit,
    ) {}

    public function record(Tire $tire, array $data, User $user): TireMeasurement
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para cargar mediciones.');
        }

        $tire->load('size.zones');
        $zones = $tire->size->zones;
        if ($zones->isEmpty()) {
            throw new DomainException('La medida no tiene franjas de profundidad configuradas.');
        }

        $readings = $data['readings'] ?? [];

        return DB::transaction(function () use ($tire, $data, $user, $zones, $readings) {
            $byCode = [];
            foreach ($readings as $reading) {
                $byCode[$reading['zone_id']] = (float) $reading['millimeters'];
            }

            $values = [];
            foreach ($zones as $zone) {
                if (! array_key_exists($zone->id, $byCode)) {
                    throw new DomainException('Falta la medición de '.$zone->name.'.');
                }
                $values[$zone->code] = $byCode[$zone->id];
            }

            $left = $values['FLANCO_IZQ'] ?? null;
            $right = $values['FLANCO_DER'] ?? null;
            $threshold = (int) $tire->size->uneven_wear_threshold_mm;
            $alert = $left !== null && $right !== null && abs($left - $right) >= $threshold;

            $measurement = TireMeasurement::create([
                'tire_id' => $tire->id,
                'measured_at' => $data['measured_at'] ?? now(),
                'unit_id' => $data['unit_id'] ?? $tire->currentLocation?->unit_id,
                'odometer' => $data['odometer'] ?? null,
                'user_id' => $user->id,
                'raises_alert' => $alert,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($zones as $zone) {
                $measurement->readings()->create([
                    'measurement_zone_id' => $zone->id,
                    'millimeters' => $byCode[$zone->id],
                ]);
            }

            $tire->update(['current_tread_min' => min($byCode)]);

            if ($alert) {
                $this->incidents->register($tire, [
                    'type' => IncidentType::DesgasteIrregular->value,
                    'occurred_at' => $measurement->measured_at,
                    'description' => "Desgaste lateral |flanco izq {$left} mm - flanco der {$right} mm| ≥ {$threshold} mm. Posible falla de la unidad.",
                    'odometer' => $measurement->odometer,
                    'unit_id' => $measurement->unit_id,
                ], $user);
            }

            $measurement->load('unit');
            $this->audit->log('tire.measured', $measurement, null, [
                'tire' => $tire->auditLabel(),
                'unit' => $measurement->unit?->plate,
                'odometer' => $measurement->odometer,
                'min_mm' => min($byCode),
            ]);

            return $measurement->load('readings.zone');
        });
    }
}
