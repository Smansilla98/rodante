<?php

namespace App\Models;

use App\Enums\TireApplication;
use App\Enums\TireCondition;
use App\Enums\TireStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TireFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tire extends Model
{
    /** @use HasFactory<TireFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'public_token', 'individual_number', 'dot', 'tire_brand_id', 'tire_model_id', 'tire_size_id',
        'tire_purchase_item_id', 'current_lifecycle_id', 'status', 'condition', 'recap_wear',
        'accumulated_km', 'current_tread_min', 'purchased_at', 'retired_at', 'notes',
    ];

    /**
     * `display_condition` viaja en TODA serialización JSON de Tire (API v1 → mobile) sin
     * tocar cada controller uno por uno — así web y mobile nunca pueden mostrar dos
     * vocabularios de estado distintos por un endpoint que se olvidó de agregarlo.
     */
    protected $appends = ['display_condition'];

    protected function casts(): array
    {
        return [
            'status' => TireStatus::class,
            'condition' => TireCondition::class,
            'purchased_at' => 'date',
            'retired_at' => 'date',
            'current_tread_min' => 'decimal:1',
        ];
    }

    public function displayName(): string
    {
        $code = $this->model?->code ?? 'S/M';

        return $code.' Nº'.$this->individual_number;
    }

    /**
     * Etiqueta compuesta única para mostrar en web/mobile: combina `status` (ubicación)
     * y `condition` (estado físico) — nunca dos listas de estados distintas entre
     * plataformas. Reusa `TireCondition::label()` (fuente de verdad existente), no
     * inventa vocabulario nuevo.
     *
     * `status` pisa a `condition` cuando importa más operativamente (una cubierta en
     * reparación se muestra "A reparar" sin importar su condición previa; "De baja" es
     * siempre terminal). Para "Recapada" agrega Nueva/Usada (`recap_wear`, clasificación
     * MANUAL por cantidad de uso — ver `setRecapWear` en TireApiController, no se calcula
     * solo, a diferencia de Nueva→Usada que sí es automático por km) y Lineal/Motriz según
     * la aplicación del modelo actual — ver `recapPatternLabel()`.
     */
    protected function displayCondition(): Attribute
    {
        return Attribute::make(get: function (): string {
            if ($this->status === TireStatus::DeBaja) {
                return 'Baja';
            }
            if ($this->status === TireStatus::EnReparacion) {
                return 'A reparar';
            }
            if ($this->condition === TireCondition::Recapada) {
                $wear = match ($this->recap_wear) {
                    'NUEVA' => 'Nueva',
                    'USADA' => 'Usada',
                    default => null,
                };
                $pattern = $this->recapPatternLabel();

                return implode(' ', array_filter(['Recapada', $wear, $pattern ?: null]));
            }

            return $this->condition->label();
        });
    }

    /**
     * "Lineal" (arrastre/dirección — tren no motriz) o "Motriz" (tracción/mixta) según
     * la aplicación del MODELO actual de la cubierta (`TireApplication`, ya existente).
     * No es un estado nuevo: es la misma clasificación que ya se usa para compatibilidad
     * de posiciones (`PositionFitService`), solo expuesta también en el estado visible.
     */
    private function recapPatternLabel(): string
    {
        return match ($this->model?->application) {
            TireApplication::Arrastre, TireApplication::Direccion => 'Lineal',
            TireApplication::Traccion, TireApplication::Mixto => 'Motriz',
            default => '',
        };
    }

    /**
     * Normaliza el DOT de fábrica (garantía): mayúsculas, sin espacios ni guiones.
     */
    public static function normalizeDot(?string $dot): ?string
    {
        if ($dot === null) {
            return null;
        }
        $clean = strtoupper(preg_replace('/[\s\-]+/', '', trim($dot)) ?? '');

        return $clean === '' ? null : $clean;
    }

    /**
     * Semana y año de fabricación a partir de los últimos 4 dígitos del DOT (WWYY).
     *
     * @return array{week: int, year: int}|null
     */
    public function manufactureWeekYear(): ?array
    {
        $dot = (string) ($this->dot ?? '');
        if (strlen($dot) < 4 || ! preg_match('/(\d{4})$/', $dot, $m)) {
            return null;
        }
        $week = (int) substr($m[1], 0, 2);
        $year = (int) substr($m[1], 2, 2);
        if ($week < 1 || $week > 53) {
            return null;
        }
        $fullYear = $year >= 70 ? 1900 + $year : 2000 + $year;

        return ['week' => $week, 'year' => $fullYear];
    }

    public function manufactureLabel(): ?string
    {
        $parsed = $this->manufactureWeekYear();
        if (! $parsed) {
            return null;
        }

        return 'Semana '.$parsed['week'].' / '.$parsed['year'];
    }

    public function auditLabel(): string
    {
        $this->loadMissing('model');
        $label = 'Nº '.$this->individual_number;

        return $this->model?->code ? $label.' ('.$this->model->code.')' : $label;
    }

    public function treadTone(): string
    {
        if ($this->current_tread_min === null) {
            return 'unknown';
        }

        $mm = (float) $this->current_tread_min;
        if ($mm <= 4) {
            return 'critical';
        }
        if ($mm <= 8) {
            return 'warn';
        }

        return 'ok';
    }

    public function fullName(): string
    {
        $brand = $this->brand?->name ?? '';

        return trim($brand.' '.$this->displayName());
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(TireBrand::class, 'tire_brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(TireModel::class, 'tire_model_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(TireSize::class, 'tire_size_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(TirePurchaseItem::class, 'tire_purchase_item_id');
    }

    public function currentLifecycle(): BelongsTo
    {
        return $this->belongsTo(TireLifecycle::class, 'current_lifecycle_id');
    }

    public function ensureOpenLifecycle(): TireLifecycle
    {
        $current = $this->currentLifecycle;
        if ($current && $current->ended_at === null) {
            return $current;
        }

        $life = TireLifecycle::create([
            'tire_id' => $this->id,
            'life_number' => max(1, (int) $this->lifecycles()->max('life_number') + 1),
            'started_by' => 'COMPRA',
            'started_at' => now(),
            'condition_at_start' => $this->condition?->value ?? TireCondition::Nueva->value,
        ]);
        $this->update(['current_lifecycle_id' => $life->id]);

        return $life;
    }

    public function lifecycles(): HasMany
    {
        return $this->hasMany(TireLifecycle::class);
    }

    public function currentLocation(): HasOne
    {
        return $this->hasOne(TireCurrentLocation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TireMovement::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(TireIncident::class)->orderByDesc('occurred_at');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(TireMeasurement::class)->orderByDesc('measured_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TireAssignment::class);
    }

    public function openAssignment(): HasOne
    {
        return $this->hasOne(TireAssignment::class)->whereNull('ended_at');
    }

    public function numberChanges(): HasMany
    {
        return $this->hasMany(TireNumberChange::class)->orderByDesc('id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function costEntries(): HasMany
    {
        return $this->hasMany(CostEntry::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TirePhoto::class)->orderBy('captured_at');
    }

    public function scopeInstallable(Builder $query): Builder
    {
        return $query->where('status', TireStatus::Stock);
    }
}
