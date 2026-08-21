<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function postWithCsrf(string $route, array $data = [])
    {
        $token = 'test-csrf-token';

        return $this->withSession(['_token' => $token])
            ->post($route, array_merge(['_token' => $token], $data));
    }

    public function test_forgot_form_renders(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Olvidé mi contraseña');
    }

    public function test_request_does_not_reveal_missing_user(): void
    {
        Notification::fake();
        $this->postWithCsrf(route('password.email'), ['login' => 'no-existe'])
            ->assertRedirect()
            ->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_active_user_with_email_receives_reset_notification(): void
    {
        Notification::fake();
        Company::create(['name' => 'Demo', 'slug' => 'demo-pw', 'is_active' => true]);
        $user = User::factory()->create([
            'email' => 'jefe@example.com',
            'username' => 'jefe-reset',
            'is_active' => true,
            'password' => Hash::make('password123a'),
        ]);

        $this->postWithCsrf(route('password.email'), ['login' => 'jefe-reset'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_inactive_user_does_not_receive_mail_but_same_message(): void
    {
        Notification::fake();
        Company::create(['name' => 'Demo2', 'slug' => 'demo-pw2', 'is_active' => true]);
        User::factory()->create([
            'email' => 'off@example.com',
            'username' => 'off-user',
            'is_active' => false,
        ]);

        $this->postWithCsrf(route('password.email'), ['login' => 'off-user'])
            ->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_reset_with_valid_token_changes_password(): void
    {
        Company::create(['name' => 'Demo3', 'slug' => 'demo-pw3', 'is_active' => true]);
        $user = User::factory()->create([
            'email' => 'ok@example.com',
            'username' => 'ok-user',
            'is_active' => true,
            'password' => Hash::make('oldpassword1'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->postWithCsrf(route('password.update'), [
            'token' => $token,
            'email' => 'ok@example.com',
            'password' => 'NuevaClave99',
            'password_confirmation' => 'NuevaClave99',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NuevaClave99', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'ok@example.com']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        Company::create(['name' => 'Demo4', 'slug' => 'demo-pw4', 'is_active' => true]);
        User::factory()->create(['email' => 'x@example.com', 'is_active' => true]);
        $this->postWithCsrf(route('password.update'), [
            'token' => 'token-falso',
            'email' => 'x@example.com',
            'password' => 'NuevaClave99',
            'password_confirmation' => 'NuevaClave99',
        ])->assertSessionHasErrors('email');
    }
}
