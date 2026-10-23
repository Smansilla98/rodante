<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_with_login_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Gestión inteligente de neumáticos para flotas')
            ->assertSee(route('login'), false);
    }

    public function test_authenticated_user_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Consulta]);

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }
}
