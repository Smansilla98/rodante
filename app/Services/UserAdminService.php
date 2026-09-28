<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAdminService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, User $actor): User
    {
        $data = $this->validate($input, $actor);
        $user = User::create([
            'company_id' => $actor->company_id,
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
        ]);
        $user->fleets()->sync($data['fleet_ids'] ?? []);
        $user->bases()->sync($data['base_ids'] ?? []);

        return $user->load('fleets', 'bases');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input, User $actor): User
    {
        $data = $this->validate($input, $actor, $user);
        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'],
            'is_active' => filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        $user->update($payload);
        $user->fleets()->sync($data['fleet_ids'] ?? []);
        $user->bases()->sync($data['base_ids'] ?? []);

        return $user->load('fleets', 'bases');
    }

    /**
     * @return array{blocked: bool, deleted: bool, inactivated: bool, message: string}
     */
    public function remove(User $user, User $actor): array
    {
        if ($user->is($actor)) {
            return [
                'blocked' => true,
                'deleted' => false,
                'inactivated' => false,
                'message' => 'No podés eliminar tu propio usuario.',
            ];
        }

        try {
            $user->fleets()->detach();
            $user->bases()->detach();
            $user->delete();
        } catch (QueryException) {
            $user->update(['is_active' => false]);

            return [
                'blocked' => false,
                'deleted' => false,
                'inactivated' => true,
                'message' => 'El usuario tiene historial: quedó inactivo para conservarlo.',
            ];
        }

        return [
            'blocked' => false,
            'deleted' => true,
            'inactivated' => false,
            'message' => 'Usuario eliminado.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, User $actor, ?User $user = null): array
    {
        $password = $user
            ? ['nullable', 'string', Password::defaults()]
            : ['required', 'string', Password::defaults()];

        return Validator::make($input, [
            'name' => 'required|string|max:80',
            'username' => ['required', 'string', 'max:40', Rule::unique('users', 'username')->where(fn ($q) => $q->where('company_id', $actor->company_id))->ignore($user)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->where(fn ($q) => $q->where('company_id', $actor->company_id))->ignore($user)],
            'password' => $password,
            'role' => ['required', Rule::enum(UserRole::class)],
            'fleet_ids' => 'array',
            'fleet_ids.*' => 'exists:fleets,id',
            'base_ids' => 'array',
            'base_ids.*' => 'exists:bases,id',
        ])->validate();
    }
}
