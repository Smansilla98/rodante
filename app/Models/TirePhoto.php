<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TirePhoto extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'tire_id', 'kind', 'path', 'original_name',
        'mime', 'size', 'user_id', 'captured_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function tire(): BelongsTo
    {
        return $this->belongsTo(Tire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'RETIRE' => 'Baja',
            default => $this->kind,
        };
    }
}
