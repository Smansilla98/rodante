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
            'device' => 'nullable|string|max:80',
        ]);

        if (! Auth::attempt(['username' => trim($data['username']), 'password' => $data['password']])) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 422);
        }

        $user = $request->user();
        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'La cuenta está desactivada.'], 403);
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
