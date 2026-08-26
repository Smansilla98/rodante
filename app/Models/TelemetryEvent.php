<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryEvent extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'source', 'path',
        'context', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->name) {
            'auth.login' => 'Ingreso',
            'field.identify' => 'Identificó en campo',
            'tire.operation' => 'Operó planilla',
            'tire.measured' => 'Medición',
            'tire.incident' => 'Incidencia',
            'tire.retired' => 'Baja',
            'tire.life_report' => 'Informe de vida',
            default => $this->name,
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'pwa' => 'App instalada',
            'api' => 'API',
            default => 'Web',
        };
    }
}
