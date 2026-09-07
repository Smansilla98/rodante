<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'company_id' => 'nullable|integer|exists:companies,id',
            'device' => 'nullable|string|max:80',
        ]);

        $username = trim($data['username']);
        $matches = \App\Models\User::query()->where('username', $username)->get();
        if ($matches->isEmpty()) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 422);
        }
        if ($matches->count() > 1 && empty($data['company_id'])) {
            return response()->json([
                'message' => 'Indicá company_id: ese usuario existe en más de una empresa.',
                'companies' => $matches->map(fn ($u) => [
                    'id' => $u->company_id,
                    'name' => $u->company?->name,
                ])->unique('id')->values(),
            ], 422);
        }
        $companyId = (int) ($data['company_id'] ?? $matches->first()->company_id);

        if (! Auth::attempt([
            'username' => $username,
            'password' => $data['password'],
            'company_id' => $companyId,
        ])) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 422);
        }

        $user = $request->user();
        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'La cuenta está desactivada.'], 403);
        }
        if (! $user->company?->is_active) {
            Auth::logout();

            return response()->json(['message' => 'La empresa está desactivada.'], 403);
        }

        $token = $user->createToken($data['device'] ?? 'campo', ['*'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role->value,
                'company_id' => $user->company_id,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if (is_object($token) && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Sesión de campo cerrada.']);
    }
}
