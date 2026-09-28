<?php

namespace App\Http\Controllers\User;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Base;
use App\Models\Fleet;
use App\Models\User;
use App\Services\UserAdminService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $users = User::with('fleets', 'bases')
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name');
        $fleets = Fleet::orderBy('name');
        AccessScope::applyCompany($fleets, $request->user());
        $bases = Base::orderBy('name');
        AccessScope::applyCompany($bases, $request->user());

        return view('users.index', [
            'users' => $users->get(),
            'roles' => UserRole::cases(),
            'fleets' => $fleets->get(),
            'bases' => $bases->get(),
        ]);
    }

    public function store(Request $request, UserAdminService $users)
    {
        $this->authorize('viewAny', User::class);
        $users->create($request->all(), $request->user());

        return back()->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $user, UserAdminService $users)
    {
        $this->authorize('manage', $user);
        abort_unless((int) $user->company_id === (int) $request->user()->company_id, 404);
        $users->update($user, $request->all(), $request->user());

        return back()->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user, UserAdminService $users)
    {
        $this->authorize('manage', $user);
        abort_unless((int) $user->company_id === (int) $request->user()->company_id, 404);
        $result = $users->remove($user, $request->user());
        if ($result['blocked']) {
            return back()->withErrors(['delete' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }
}
