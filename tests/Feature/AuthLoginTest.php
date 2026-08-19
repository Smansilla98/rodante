<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_demo_credentials(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'password',
            'role' => UserRole::Administrador,
            'is_active' => true,
        ]);

        $this->get('/login');
        $this->post('/login', [
            'username' => 'admin',
            'password' => 'password',
            '_token' => csrf_token(),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'password',
            'role' => UserRole::Administrador,
        ]);

        $this->get('/login');
        $this->from('/login')->post('/login', [
            'username' => 'admin',
            'password' => 'otra',
            '_token' => csrf_token(),
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->create([
            'username' => 'baja',
            'password' => 'password',
            'role' => UserRole::Operario,
            'is_active' => false,
        ]);

        $this->get('/login');
        $this->from('/login')->post('/login', [
            'username' => 'baja',
            'password' => 'password',
            '_token' => csrf_token(),
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
