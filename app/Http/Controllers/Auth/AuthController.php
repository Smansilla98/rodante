<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
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
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);
        $credentials['username'] = trim($credentials['username']);
        $credentials['password'] = trim($credentials['password']);

        $matches = User::query()
            ->where('username', $credentials['username'])
            ->with('company:id,name,slug,is_active')
            ->get();

        if ($matches->isEmpty()) {
            return back()->withErrors(['username' => 'Credenciales incorrectas.'])->onlyInput('username');
        }

        $companyChoices = fn () => $matches->map(fn (User $u) => [
            'id' => $u->company_id,
            'name' => $u->company?->name ?? ('Empresa #'.$u->company_id),
        ])->unique('id')->values();

        if ($matches->count() > 1 && empty($credentials['company_id'])) {
            return back()
                ->withErrors(['company_id' => 'Elegí la empresa: ese usuario existe en más de una.'])
                ->with('login_companies', $companyChoices())
                ->onlyInput('username');
        }

        $companyId = (int) ($credentials['company_id'] ?? $matches->first()->company_id);
        if (! $matches->contains(fn (User $u) => (int) $u->company_id === $companyId)) {
            return back()
                ->withErrors(['username' => 'Credenciales incorrectas.'])
                ->with('login_companies', $matches->count() > 1 ? $companyChoices() : collect())
                ->onlyInput('username', 'company_id');
        }

        $company = Company::query()->find($companyId);
        if (! $company || ! $company->is_active) {
            return back()
                ->withErrors(['username' => 'La empresa está desactivada.'])
                ->with('login_companies', $matches->count() > 1 ? $companyChoices() : collect())
                ->onlyInput('username', 'company_id');
        }

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'company_id' => $companyId,
        ], $request->boolean('remember'))) {
            return back()
                ->withErrors(['username' => 'Credenciales incorrectas.'])
                ->with('login_companies', $matches->count() > 1 ? $companyChoices() : collect())
                ->onlyInput('username', 'company_id');
        }

        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['username' => 'La cuenta está desactivada.']);
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $user->update(['last_login_at' => now()]);
        app(\App\Support\Tenancy\TenantContext::class)->setId((int) $user->company_id);
        app(\App\Services\TelemetryService::class)->record('auth.login', $user, [
            'username' => $user->username,
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
