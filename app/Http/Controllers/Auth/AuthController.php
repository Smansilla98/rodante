<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $credentials['username'] = trim($credentials['username']);
        $credentials['password'] = trim($credentials['password']);

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'Credenciales incorrectas.'])->onlyInput('username');
        }

        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['username' => 'La cuenta está desactivada.']);
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        app(\App\Services\TelemetryService::class)->record('auth.login', $user, [
            'username' => $user->username,
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
