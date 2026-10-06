<?php

namespace App\Services;

use App\Models\FleetUnit;
use App\Models\Tire;
use App\Models\User;
use App\Support\AccessScope;
use Illuminate\Support\Collection;

class SearchService
{
    /**
     * @return array{0: Collection<int, Tire>, 1: Collection<int, FleetUnit>}
     */
    public function hits(User $user, string $term, int $limit): array
    {
        $digits = preg_replace('/\D+/', '', $term) ?: null;
        $plate = FleetUnit::normalizePlate($term);
        $dot = Tire::normalizeDot($term);

        $tires = Tire::query()->with(['brand', 'model', 'size', 'currentLocation.unit']);
        AccessScope::tires($tires, $user);
        $tires->where(function ($q) use ($term, $digits, $dot) {
            $q->where('individual_number', 'like', "%{$term}%")
                ->orWhereHas('model', fn ($m) => $m->where('code', 'like', "%{$term}%"))
                ->orWhere('public_token', $term);
            if ($digits) {
                $q->orWhere('individual_number', 'like', "%{$digits}%");
            }
            if ($dot) {
                $q->orWhere('dot', 'like', "%{$dot}%");
            }
        })->orderBy('individual_number')->limit($limit);

        $units = FleetUnit::query()->with('type', 'fleet');
        AccessScope::units($units, $user);
        $units->where(function ($q) use ($term, $plate) {
            $q->where('plate', 'like', "%{$term}%");
            if ($plate !== '') {
                $q->orWhereRaw("REPLACE(UPPER(plate), ' ', '') like ?", ['%'.$plate.'%']);
            }
        })->orderBy('plate')->limit($limit);

        return [$tires->get(), $units->get()];
    }
}
