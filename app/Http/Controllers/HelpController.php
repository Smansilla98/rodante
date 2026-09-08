<?php

namespace App\Http\Controllers;

use App\Support\SystemGuide;
use App\Support\UnitConfigurationCatalog;
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

    public function startingPoint()
    {
        $path = base_path('docs/punto-de-partida.md');
        abort_unless(File::exists($path), 404);

        return view('help.starting-point', [
            'html' => Str::markdown(File::get($path), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'positionExamples' => $this->positionExamples(),
        ]);
    }

    /**
     * @return list<array{code: string, name: string, description: string, positions: list<array<string, mixed>>}>
     */
    private function positionExamples(): array
    {
        $codes = ['6X4', '4X2', '3E-S'];
        $examples = [];

        foreach ($codes as $code) {
            $layout = UnitConfigurationCatalog::poweredByCode($code);
            if ($layout === null) {
                foreach (UnitConfigurationCatalog::trailers() as $trailer) {
                    if ($trailer['code'] === $code) {
                        $layout = $trailer;
                        break;
                    }
                }
            }
            if ($layout === null) {
                continue;
            }
            $examples[] = [
                'code' => $layout['code'],
                'name' => $layout['name'],
                'description' => $layout['description'] ?? '',
                'positions' => UnitConfigurationCatalog::positionRows($layout),
            ];
        }

        return $examples;
    }
}
