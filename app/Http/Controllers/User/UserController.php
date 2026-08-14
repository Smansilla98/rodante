<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::with('fleets', 'bases')->orderBy('name')->get(),
            'roles' => UserRole::cases(),
            'fleets' => Fleet::orderBy('name')->get(),
            'bases' => Base::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
        ]);
        $user->fleets()->sync($data['fleet_ids'] ?? []);
        $user->bases()->sync($data['base_ids'] ?? []);

        return back()->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        $user->update($payload);
        $user->fleets()->sync($data['fleet_ids'] ?? []);
        $user->bases()->sync($data['base_ids'] ?? []);

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['delete' => 'No podés eliminar tu propio usuario.']);
        }
        try {
            $user->fleets()->detach();
            $user->bases()->detach();
            $user->delete();
        } catch (QueryException) {
            $user->update(['is_active' => false]);

            return back()->with('success', 'El usuario tiene historial: quedó inactivo para conservarlo.');
        }

        return back()->with('success', 'Usuario eliminado.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $password = $user ? 'nullable|string|min:6' : 'required|string|min:6';

        return $request->validate([
            'name' => 'required|string|max:80',
            'username' => ['required', 'string', 'max:40', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user)],
            'password' => $password,
            'role' => ['required', Rule::enum(UserRole::class)],
            'fleet_ids' => 'array',
            'fleet_ids.*' => 'exists:fleets,id',
            'base_ids' => 'array',
            'base_ids.*' => 'exists:bases,id',
        ]);
    }
}
