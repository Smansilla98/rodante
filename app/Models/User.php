<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    public function fleets(): BelongsToMany
    {
        return $this->belongsToMany(Fleet::class, 'user_fleet_access');
    }

    public function bases(): BelongsToMany
    {
        return $this->belongsToMany(Base::class, 'user_base_access');
    }

    public function canAccessFleet(?int $fleetId): bool
    {
        if ($this->role === UserRole::Administrador) {
            return true;
        }

        return $fleetId !== null && $this->fleets()->where('fleets.id', $fleetId)->exists();
    }

    public function canAccessBase(?int $baseId): bool
    {
        if ($this->role === UserRole::Administrador) {
            return true;
        }

        return $baseId !== null && $this->bases()->where('bases.id', $baseId)->exists();
    }
}
