<?php

namespace App\Http\Controllers;

use App\Support\SystemGuide;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HelpController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $role = $user->role;

        return view('help.index', [
            'profile' => SystemGuide::forRole($role),
            'modules' => SystemGuide::modulesFor($role),
            'allRoles' => SystemGuide::roles(),
            'matrixRoles' => SystemGuide::matrixRoles(),
            'matrixModules' => SystemGuide::modules(),
        ]);
    }

    public function manual()
    {
        $path = base_path('docs/manual-de-uso.md');
        abort_unless(File::exists($path), 404);

        return view('help.manual', [
            'html' => Str::markdown(File::get($path), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
