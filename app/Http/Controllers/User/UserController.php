<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\User;
use Illuminate\Http\Request;

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
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'username' => 'required|string|max:40|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
            'fleet_ids' => 'array',
            'base_ids' => 'array',
        ]);
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
}
