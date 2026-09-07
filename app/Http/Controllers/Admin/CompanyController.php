<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FleetUnit;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 404);

        $companies = Company::query()
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) {
                return [
                    'model' => $company,
                    'users_count' => User::query()->where('company_id', $company->id)->count(),
                    'units_count' => FleetUnit::withoutTenant()->where('company_id', $company->id)->count(),
                ];
            });

        return view('admin.companies.index', ['companies' => $companies]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()?->is_super_admin, 404);

        return view('admin.companies.create');
    }

    public function store(Request $request, CompanyProvisioningService $provisioning)
    {
        abort_unless($request->user()?->is_super_admin, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('companies', 'slug')],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_username' => ['required', 'string', 'max:40'],
            'admin_email' => ['nullable', 'email'],
        ], [
            'slug.unique' => 'Ese código de empresa ya existe.',
            'admin_username.required' => 'Indicá el usuario administrador inicial.',
        ]);

        $result = $provisioning->provision(
            [
                'name' => $data['name'],
                'slug' => Str::lower($data['slug']),
                'tax_id' => $data['tax_id'] ?? null,
            ],
            [
                'name' => $data['admin_name'],
                'username' => $data['admin_username'],
                'email' => $data['admin_email'] ?? null,
            ]
        );

        return redirect()
            ->route('admin.companies.index')
            ->with('success', 'Empresa creada. Usuario '.$result['admin']->username.' — contraseña temporal: '.$result['plain_password']);
    }

    public function toggle(Request $request, Company $company)
    {
        abort_unless($request->user()?->is_super_admin, 404);

        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('success', $company->is_active ? 'Empresa activada.' : 'Empresa desactivada.');
    }
}
