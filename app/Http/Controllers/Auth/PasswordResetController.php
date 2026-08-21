<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:120'],
        ]);

        $login = trim($data['login']);
        $user = User::query()
            ->where(function ($q) use ($login) {
                $q->where('username', $login)->orWhere('email', $login);
            })
            ->first();

        // Anti-enumeración: siempre el mismo mensaje y status.
        $okMessage = 'Si la cuenta existe y tiene un email activo, vas a recibir instrucciones para restablecer la contraseña.';

        if ($user && $user->is_active && filled($user->email)) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', $okMessage);
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                if (! $user->is_active) {
                    return;
                }
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya podés ingresar.');
        }

        // Token inválido/expirado: no revelar si el email existe.
        return back()->withErrors(['email' => 'El enlace no es válido o expiró. Pedí uno nuevo.'])->withInput($request->only('email'));
    }
}
