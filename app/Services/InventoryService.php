<?php

namespace App\Services;

use App\Enums\InventoryLineDelta;
use App\Enums\InventorySessionStatus;
use App\Enums\LocationKind;
use App\Enums\TireStatus;
use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\InventoryLine;
use App\Models\InventorySession;
use App\Models\Tire;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /** Estados de depósito que entran al snapshot de una base. */
    public const STOCKABLE = [
        LocationKind::Stock->value,
        LocationKind::Reserva->value,
        LocationKind::EnReparacion->value,
    ];

    public function __construct(
        private DocumentNumberService $numbers,
        private BaseTransferService $baseTransfers,
        private AuditService $audit,
    ) {}

    public function open(User $user, Base $base, ?string $notes = null): InventorySession
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para abrir un inventario.');
        }
        if ((int) $base->company_id !== (int) $user->company_id) {
            throw new DomainException('La base no pertenece a tu empresa.');
        }
        if (! AccessScope::seesEverything($user)
            && ! in_array((int) $base->id, AccessScope::visibleBaseIds($user), true)) {
            throw new DomainException('No tenés acceso a esa base.');
        }

        $active = InventorySession::query()
            ->where('base_id', $base->id)
            ->whereIn('status', [
                InventorySessionStatus::Open->value,
                InventorySessionStatus::Counting->value,
                InventorySessionStatus::Review->value,
            ])
            ->exists();
        if ($active) {
            throw new DomainException('Ya hay un inventario abierto en esa base. Cerralo o cancelalo antes.');
        }

        return DB::transaction(function () use ($user, $base, $notes) {
            $session = InventorySession::create([
                'company_id' => $user->company_id,
                'base_id' => $base->id,
                'number' => $this->numbers->next((int) $user->company_id, 'inventory', 'INV-'),
                'status' => InventorySessionStatus::Open,
                'notes' => $notes,
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);

            $tires = Tire::query()
                ->where('company_id', $user->company_id)
                ->whereIn('status', self::STOCKABLE)
                ->whereHas('currentLocation', fn ($q) => $q
                    ->where('base_id', $base->id)
                    ->whereIn('location_kind', self::STOCKABLE))
                ->with('currentLocation')
                ->orderBy('individual_number')
                ->get();

            foreach ($tires as $tire) {
                $loc = $tire->currentLocation;
                InventoryLine::create([
                    'inventory_session_id' => $session->id,
                    'tire_id' => $tire->id,
                    'expected_kind' => $loc?->location_kind?->value ?? $tire->status->value,
                    'expected_base_id' => $loc?->base_id,
                    'expected_unit_id' => $loc?->unit_id,
                    'in_snapshot' => true,
                    'found' => false,
                ]);
            }

            $session->update(['expected_count' => $tires->count()]);
            $this->audit->log('inventory.opened', $session->fresh(), null, [
                'base' => $base->name,
                'expected' => $tires->count(),
            ]);

            return $session->fresh(['base', 'lines']);
        });
    }

    public function startCounting(InventorySession $session, User $user): InventorySession
    {
        $this->assertCanOperate($session, $user);
        if ($session->status !== InventorySessionStatus::Open) {
            throw new DomainException('Solo se inicia el conteo desde una sesión abierta.');
        }

        $session->update([
            'status' => InventorySessionStatus::Counting,
            'counting_started_at' => now(),
        ]);
        $this->audit->log('inventory.counting', $session);

        return $session->fresh();
    }

    public function scan(InventorySession $session, User $user, string $query): InventoryLine
    {
        $this->assertCanOperate($session, $user);
        if (! $session->status->canScan()) {
            throw new DomainException('El conteo no está activo. Pasá la sesión a “En conteo”.');
        }

        $tire = $this->resolveTire($user, $query);
        if (! $tire) {
            throw new DomainException('No se encontró una cubierta con “'.$query.'”.');
        }

        return DB::transaction(function () use ($session, $user, $tire) {
            $session = InventorySession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $tire->load('currentLocation');
            $loc = $tire->currentLocation;
            $kind = $loc?->location_kind ?? LocationKind::tryFrom($tire->status->value);
            $baseId = $loc?->base_id;

            $line = InventoryLine::query()
                ->where('inventory_session_id', $session->id)
                ->where('tire_id', $tire->id)
                ->lockForUpdate()
                ->first();

            if ($line && $line->found) {
                throw new DomainException($tire->displayName().' ya fue contada en este inventario.');
            }

            $delta = $this->deltaForScan($session, $tire, $kind, $baseId);

            if (! $line) {
                $line = InventoryLine::create([
                    'inventory_session_id' => $session->id,
                    'tire_id' => $tire->id,
                    'expected_kind' => null,
                    'expected_base_id' => $baseId,
                    'expected_unit_id' => $loc?->unit_id,
                    'in_snapshot' => false,
                    'found' => true,
                    'delta' => $delta,
                    'observed_kind' => $kind?->value,
                    'observed_base_id' => $session->base_id,
                    'scanned_at' => now(),
                    'scanned_by' => $user->id,
                    'notes' => $delta === InventoryLineDelta::Mounted
                        ? 'Está montada o fuera de depósito; no se ajusta desde inventario.'
                        : null,
                ]);
            } else {
                $line->update([
                    'found' => true,
                    'delta' => $delta,
                    'observed_kind' => $kind?->value,
                    'observed_base_id' => $session->base_id,
                    'scanned_at' => now(),
                    'scanned_by' => $user->id,
                ]);
            }

            $this->refreshCounts($session);
            $this->audit->log('inventory.scanned', $line->fresh(), null, [
                'tire' => $tire->individual_number,
                'delta' => $delta->value,
            ]);

            return $line->fresh(['tire.model', 'tire.brand']);
        });
    }

    public function submitForReview(InventorySession $session, User $user): InventorySession
    {
        $this->assertCanOperate($session, $user);
        if ($session->status !== InventorySessionStatus::Counting) {
            throw new DomainException('Solo se envía a revisión desde el conteo.');
        }

        return DB::transaction(function () use ($session, $user) {
            $session = InventorySession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            InventoryLine::query()
                ->where('inventory_session_id', $session->id)
                ->where('in_snapshot', true)
                ->where('found', false)
                ->update([
                    'delta' => InventoryLineDelta::Missing->value,
                ]);

            $session->update([
                'status' => InventorySessionStatus::Review,
                'submitted_at' => now(),
            ]);
            $this->refreshCounts($session);
            $this->audit->log('inventory.review', $session->fresh(), null, [
                'by' => $user->id,
            ]);

            return $session->fresh();
        });
    }

    /**
     * Cierra la sesión. Si $applyLocationFixes, corrige base de cubiertas stockables
     * (WRONG_BASE / UNEXPECTED stockable). Nunca desmonta ni da de baja.
     */
    public function close(InventorySession $session, User $user, bool $applyLocationFixes = false, ?string $notes = null): InventorySession
    {
        if ($session->status !== InventorySessionStatus::Review) {
            throw new DomainException('Solo se cierra desde revisión.');
        }
        if ($applyLocationFixes && ! $user->role->canChangeConfiguration()) {
            throw new DomainException('Solo jefe o administrador aplican correcciones de ubicación.');
        }
        if (! $applyLocationFixes && ! $user->role->canValidateOdometer() && ! $user->role->canChangeConfiguration()) {
            throw new DomainException('No tiene permiso para cerrar el inventario.');
        }

        return DB::transaction(function () use ($session, $user, $applyLocationFixes, $notes) {
            $session = InventorySession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $fixed = 0;

            if ($applyLocationFixes) {
                $lines = InventoryLine::query()
                    ->where('inventory_session_id', $session->id)
                    ->whereIn('delta', [
                        InventoryLineDelta::WrongBase->value,
                        InventoryLineDelta::Unexpected->value,
                    ])
                    ->where('found', true)
                    ->with('tire.currentLocation')
                    ->lockForUpdate()
                    ->get();

                foreach ($lines as $line) {
                    $tire = $line->tire;
                    if (! $tire || ! in_array($tire->status->value, self::STOCKABLE, true)) {
                        continue;
                    }
                    $fromBase = $tire->currentLocation?->base_id;
                    if ((int) $fromBase === (int) $session->base_id) {
                        $line->update(['adjustment_applied' => true]);
                        continue;
                    }

                    $this->baseTransfers->transfer(
                        $tire,
                        Base::findOrFail($session->base_id),
                        $user,
                        'Ajuste inventario '.$session->number,
                    );
                    $line->update(['adjustment_applied' => true]);
                    $fixed++;
                }
            }

            $session->update([
                'status' => InventorySessionStatus::Closed,
                'closed_by' => $user->id,
                'approved_by' => $applyLocationFixes ? $user->id : $session->approved_by,
                'closed_at' => now(),
                'adjustments_applied' => $applyLocationFixes,
                'notes' => $notes ? trim(($session->notes ? $session->notes."\n" : '').$notes) : $session->notes,
            ]);
            $this->refreshCounts($session);
            $this->audit->log('inventory.closed', $session->fresh(), null, [
                'adjustments' => $applyLocationFixes,
                'fixed' => $fixed,
            ]);

            return $session->fresh();
        });
    }

    public function cancel(InventorySession $session, User $user, ?string $notes = null): InventorySession
    {
        if (! $session->status->isActive()) {
            throw new DomainException('La sesión ya está cerrada o cancelada.');
        }
        if (! $user->role->canValidateOdometer() && ! $user->role->canChangeConfiguration()) {
            throw new DomainException('No tiene permiso para cancelar el inventario.');
        }

        $session->update([
            'status' => InventorySessionStatus::Cancelled,
            'cancelled_at' => now(),
            'closed_by' => $user->id,
            'notes' => $notes ? trim(($session->notes ? $session->notes."\n" : '').$notes) : $session->notes,
        ]);
        $this->audit->log('inventory.cancelled', $session);

        return $session->fresh();
    }

    private function resolveTire(User $user, string $query): ?Tire
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        $builder = Tire::query()->where('company_id', $user->company_id);
        AccessScope::tires($builder, $user);

        $byToken = (clone $builder)->where('public_token', $q)->first();
        if ($byToken) {
            return $byToken;
        }

        if (ctype_digit($q)) {
            return (clone $builder)->where('individual_number', (int) $q)->first();
        }

        return null;
    }

    private function deltaForScan(
        InventorySession $session,
        Tire $tire,
        ?LocationKind $kind,
        ?int $baseId,
    ): InventoryLineDelta {
        if (in_array($tire->status, [TireStatus::Instalada, TireStatus::Auxilio], true)
            || in_array($kind, [LocationKind::Instalada, LocationKind::Auxilio], true)) {
            return InventoryLineDelta::Mounted;
        }
        if ($tire->status === TireStatus::DeBaja || $kind === LocationKind::DeBaja) {
            return InventoryLineDelta::Unexpected;
        }

        $inSnapshot = InventoryLine::query()
            ->where('inventory_session_id', $session->id)
            ->where('tire_id', $tire->id)
            ->where('in_snapshot', true)
            ->exists();

        if ($inSnapshot) {
            return InventoryLineDelta::Ok;
        }

        if ($baseId && (int) $baseId !== (int) $session->base_id && in_array($tire->status->value, self::STOCKABLE, true)) {
            return InventoryLineDelta::WrongBase;
        }

        return InventoryLineDelta::Unexpected;
    }

    private function refreshCounts(InventorySession $session): void
    {
        $lines = InventoryLine::query()->where('inventory_session_id', $session->id)->get();
        $session->update([
            'found_count' => $lines->where('found', true)->count(),
            'missing_count' => $lines->where('delta', InventoryLineDelta::Missing)->count(),
            'unexpected_count' => $lines->whereIn('delta', [
                InventoryLineDelta::Unexpected,
                InventoryLineDelta::WrongBase,
                InventoryLineDelta::Mounted,
            ])->count(),
            'expected_count' => $lines->where('in_snapshot', true)->count(),
        ]);
    }

    private function assertCanOperate(InventorySession $session, User $user): void
    {
        if (! $user->role->canWrite()) {
            throw new DomainException('No tiene permiso para operar el inventario.');
        }
        if ((int) $session->company_id !== (int) $user->company_id) {
            throw new DomainException('El inventario no pertenece a tu empresa.');
        }
        if (! AccessScope::seesEverything($user)
            && ! in_array((int) $session->base_id, AccessScope::visibleBaseIds($user), true)) {
            throw new DomainException('No tenés acceso a la base de este inventario.');
        }
    }
}
