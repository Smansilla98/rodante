<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
        if ($fleetId === null) {
            return false;
        }
        $fleet = Fleet::query()->find($fleetId);
        if (! $fleet || (int) $fleet->company_id !== (int) $this->company_id) {
            return false;
        }
        if ($this->role === UserRole::Administrador) {
            return true;
        }

        return $this->fleets()->where('fleets.id', $fleetId)->exists();
    }

    public function canAccessBase(?int $baseId): bool
    {
        if ($baseId === null) {
            return false;
        }
        $base = Base::query()->find($baseId);
        if (! $base || (int) $base->company_id !== (int) $this->company_id) {
            return false;
        }
        if ($this->role === UserRole::Administrador) {
            return true;
        }

        return $this->bases()->where('bases.id', $baseId)->exists();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
