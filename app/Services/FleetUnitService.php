<?php

namespace App\Services;

use App\Enums\UnitDuty;
use App\Enums\UnitStatus;
use App\Models\Fleet;
use App\Models\FleetUnit;
use App\Models\UnitConfiguration;
use App\Models\UnitCoupling;
use App\Models\UnitType;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FleetUnitService
{
    /**
     * Alta de unidad. Misma validación que la pantalla web de unidades.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $user): FleetUnit
    {
        $data = $this->validate($input);
        $type = UnitType::findOrFail($data['unit_type_id']);
        $configuration = UnitConfiguration::findOrFail($data['unit_configuration_id']);
        $this->assertCompatible($configuration, $type);
        $this->assertScope($user, (int) $data['fleet_id'], (int) $data['base_id']);

        $data['status'] = UnitStatus::Activa->value;
        $data['current_odometer'] = $type->has_odometer ? ($data['current_odometer'] ?? 0) : 0;
        $data['plate'] = strtoupper(trim($data['plate']));
        $data['duty'] = $data['duty'] ?? null;
        $data['specs'] = $this->specsFor($type, $data['specs'] ?? null);

        return FleetUnit::create($data);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(FleetUnit $unit, array $input, User $user): FleetUnit
    {
        $data = $this->validate($input, $unit);
        $this->assertScope($user, (int) $data['fleet_id'], (int) $data['base_id']);

        $data['plate'] = strtoupper(trim($data['plate']));
        $data['specs'] = $this->specsFor($unit->type, $data['specs'] ?? null);
        $unit->update($data);

        return $unit->fresh();
    }

    /**
     * @return array{blocked: bool, deleted: bool, message: string}
     */
    public function remove(FleetUnit $unit): array
    {
        if ($unit->locations()->exists()) {
            return [
                'blocked' => true,
                'deleted' => false,
                'message' => 'Retirá las cubiertas antes de eliminar la unidad.',
            ];
        }

        $open = UnitCoupling::query()
            ->where(function ($query) use ($unit) {
                $query->where('tractor_id', $unit->id)->orWhere('trailer_id', $unit->id);
            })
            ->whereNull('uncoupled_at')
            ->exists();
        if ($open) {
            return [
                'blocked' => true,
                'deleted' => false,
                'message' => 'Desacoplá la unidad antes de eliminarla.',
            ];
        }

        $history = UnitCoupling::query()
            ->where(function ($query) use ($unit) {
                $query->where('tractor_id', $unit->id)->orWhere('trailer_id', $unit->id);
            })
            ->exists();
        if ($history) {
            $unit->update(['status' => UnitStatus::Inactiva]);

            return [
                'blocked' => false,
                'deleted' => false,
                'message' => 'La unidad tiene historial: quedó inactiva.',
            ];
        }

        $unit->delete();

        return [
            'blocked' => false,
            'deleted' => true,
            'message' => 'Unidad eliminada.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, ?FleetUnit $unit = null): array
    {
        $rules = [
            'fleet_id' => 'required|exists:fleets,id',
            'base_id' => 'required|exists:bases,id',
            'plate' => 'required|string|max:20|unique:fleet_units,plate'.($unit ? ','.$unit->id : ''),
            'brand' => 'nullable|string|max:40',
            'model_name' => 'nullable|string|max:40',
            'duty' => ['nullable', Rule::enum(UnitDuty::class)],
            'notes' => 'nullable|string',
            'specs' => 'nullable|array',
            'specs.capacity_l' => 'nullable|integer|min:0',
            'specs.compartments' => 'nullable|integer|min:0|max:20',
            'specs.material' => 'nullable|string|max:40',
            'specs.product' => 'nullable|string|max:80',
            'specs.suspension' => 'nullable|string|max:40',
            'specs.tire_width' => 'nullable|in:295,385',
        ];

        if ($unit) {
            $rules['status'] = 'required|in:ACTIVA,INACTIVA,SPARE';
        } else {
            $rules['unit_type_id'] = 'required|exists:unit_types,id';
            $rules['unit_configuration_id'] = 'required|exists:unit_configurations,id';
            $rules['current_odometer'] = 'nullable|integer|min:0';
        }

        return Validator::make($input, $rules)->validate();
    }

    private function assertCompatible(UnitConfiguration $configuration, UnitType $type): void
    {
        if ($configuration->isCompatibleWith($type)) {
            return;
        }

        throw ValidationException::withMessages([
            'unit_configuration_id' => 'Esa configuración no aplica al tipo '.$type->name.'.',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $specs
     * @return array<string, mixed>|null
     */
    private function specsFor(UnitType $type, ?array $specs): ?array
    {
        if ($type->has_odometer) {
            return null;
        }

        if (empty($specs['tire_width'] ?? null)) {
            throw ValidationException::withMessages([
                'specs.tire_width' => 'En tanque, semi o batea hay que indicar si lleva lineal 295 o 385.',
            ]);
        }

        $specs = array_filter($specs ?? [], fn ($value) => $value !== null && $value !== '');

        return $specs ?: null;
    }

    private function assertScope(User $user, int $fleetId, int $baseId): void
    {
        if (AccessScope::seesEverything($user)) {
            return;
        }

        $fleets = AccessScope::fleetIds($user);
        $bases = AccessScope::visibleBaseIds($user);
        $errors = [];
        if ($fleets === [] && $bases === []) {
            $errors['fleet_id'] = 'No tenés flotas asignadas para dar de alta una unidad.';
        }
        if ($fleets !== [] && ! in_array($fleetId, $fleets, true)) {
            $errors['fleet_id'] = 'Esa flota no está en tu alcance.';
        }
        if ($bases !== [] && ! in_array($baseId, $bases, true)) {
            $errors['base_id'] = 'Esa base no está en tu alcance.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if (! Fleet::query()->whereKey($fleetId)->exists()) {
            throw ValidationException::withMessages([
                'fleet_id' => 'Esa flota no está en tu alcance.',
            ]);
        }
    }
}
