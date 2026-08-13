<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\FleetUnit;
use App\Models\TireAssignment;
use App\Models\TireAssignmentSegment;
use App\Models\UnitCoupling;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CouplingService
{
    public function __construct(
        private OdometerService $odometers,
        private LocationService $locations,
        private AuditService $audit,
    ) {}

    public function resolveOdometerUnit(FleetUnit $unit): FleetUnit
    {
        $unit->loadMissing('type', 'currentCouplingAsTrailer.tractor.type');

        if ($unit->hasOdometer()) {
            return $unit;
        }

        $coupling = $unit->currentCouplingAsTrailer;
        if (! $coupling) {
            throw new DomainException('El acoplado '.$unit->plate.' no tiene tractor asignado. Acoplalo antes de registrar km.');
        }

        return $coupling->tractor;
    }

    public function couple(FleetUnit $tractor, FleetUnit $trailer, int $odometer, User $user, ?string $notes = null): UnitCoupling
    {
        if (! $tractor->hasOdometer()) {
            throw new DomainException('Solo un tractor puede tirar un acoplado.');
        }
        if ($trailer->hasOdometer()) {
            throw new DomainException('No se puede acoplar un tractor como semi/tanque/batea.');
        }

        return DB::transaction(function () use ($tractor, $trailer, $odometer, $user, $notes) {
            $tractor = FleetUnit::lockForUpdate()->findOrFail($tractor->id);
            $trailer = FleetUnit::lockForUpdate()->findOrFail($trailer->id);

            $this->odometers->assertNotDecreasing($tractor, $odometer);

            $openTrailer = UnitCoupling::where('trailer_id', $trailer->id)->whereNull('uncoupled_at')->lockForUpdate()->first();
            if ($openTrailer) {
                $this->uncouple($openTrailer, $odometer, $user, 'Reemplazo de acoplamiento');
            }

            $openTractor = UnitCoupling::where('tractor_id', $tractor->id)->whereNull('uncoupled_at')->lockForUpdate()->first();
            if ($openTractor) {
                $this->uncouple($openTractor, $odometer, $user, 'El tractor cambia de acoplado');
            }

            $coupling = UnitCoupling::create([
                'tractor_id' => $tractor->id,
                'trailer_id' => $trailer->id,
                'tractor_odometer_start' => $odometer,
                'coupled_at' => now(),
                'user_id' => $user->id,
                'notes' => $notes,
                'open_trailer_key' => $trailer->id,
                'open_tractor_key' => $tractor->id,
            ]);

            $this->odometers->record($tractor, $odometer, $user, null, 'Acoplamiento con '.$trailer->plate);

            $this->openSegmentsForTrailer($trailer, $tractor, $odometer);

            $this->audit->log('coupling.created', $coupling, null, [
                'tractor' => $tractor->plate,
                'trailer' => $trailer->plate,
                'odometer' => $odometer,
            ]);

            return $coupling;
        });
    }

    public function uncouple(UnitCoupling $coupling, int $odometer, User $user, ?string $notes = null): UnitCoupling
    {
        if (! $coupling->isOpen()) {
            throw new DomainException('El acoplamiento ya está cerrado.');
        }

        return DB::transaction(function () use ($coupling, $odometer, $user, $notes) {
            $coupling = UnitCoupling::lockForUpdate()->findOrFail($coupling->id);
            $tractor = FleetUnit::lockForUpdate()->findOrFail($coupling->tractor_id);

            $this->odometers->assertNotDecreasing($tractor, $odometer);
            if ($odometer < $coupling->tractor_odometer_start) {
                throw new DomainException('El odómetro de desacople no puede ser menor al de acople.');
            }

            $this->closeSegmentsForTrailer($coupling->trailer_id, $odometer);

            $coupling->update([
                'tractor_odometer_end' => $odometer,
                'uncoupled_at' => now(),
                'open_trailer_key' => null,
                'open_tractor_key' => null,
                'notes' => trim(($coupling->notes ? $coupling->notes.' | ' : '').($notes ?? '')),
            ]);

            $coupling->loadMissing('trailer');
            $this->odometers->record($tractor, $odometer, $user, null, 'Desacople de '.$coupling->trailer->plate);
            $this->audit->log('coupling.closed', $coupling);

            return $coupling->refresh();
        });
    }

    private function openSegmentsForTrailer(FleetUnit $trailer, FleetUnit $tractor, int $odometer): void
    {
        $assignments = TireAssignment::query()
            ->where('unit_id', $trailer->id)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->openSegment) {
                continue;
            }

            TireAssignmentSegment::create([
                'tire_assignment_id' => $assignment->id,
                'odometer_unit_id' => $tractor->id,
                'start_odometer' => $odometer,
                'counts_km' => $assignment->counts_km,
                'started_at' => now(),
                'open_key' => $assignment->id,
            ]);
        }
    }

    private function closeSegmentsForTrailer(int $trailerId, int $odometer): void
    {
        $assignments = TireAssignment::query()
            ->where('unit_id', $trailerId)
            ->whereNull('ended_at')
            ->with('openSegment')
            ->lockForUpdate()
            ->get();

        foreach ($assignments as $assignment) {
            $segment = $assignment->openSegment;
            if (! $segment) {
                continue;
            }
            $this->closeSegment($segment, $odometer);
            $this->locations->refreshAccumulatedKm($assignment->tire);
        }
    }

    public function closeSegment(TireAssignmentSegment $segment, int $odometer): int
    {
        if ($odometer < $segment->start_odometer) {
            throw new DomainException('El odómetro de cierre ('.$odometer.') es menor al de apertura ('.$segment->start_odometer.').');
        }

        $km = $segment->counts_km ? ($odometer - $segment->start_odometer) : 0;

        $segment->update([
            'end_odometer' => $odometer,
            'km_delta' => $km,
            'ended_at' => now(),
            'open_key' => null,
        ]);

        return $km;
    }
}
