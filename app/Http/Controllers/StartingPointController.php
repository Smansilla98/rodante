<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\Base;
use App\Services\OpeningStockService;
use App\Support\AccessScope;
use App\Support\UnitConfigurationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StartingPointController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $bases = AccessScope::seesEverything($user)
            ? Base::orderBy('name')->get()
            : Base::whereIn('id', AccessScope::visibleBaseIds($user))->orderBy('name')->get();

        $path = base_path('docs/punto-de-partida.md');
        abort_unless(File::exists($path), 404);

        return view('starting-point.index', [
            'bases' => $bases,
            'html' => Str::markdown(File::get($path), [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'positionExamples' => $this->positionExamples(),
            'canWrite' => $user->role->canWrite(),
        ]);
    }

    public function importStock(Request $request, OpeningStockService $opening)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
            'base_id' => 'required|exists:bases,id',
            'as_of' => 'required|date',
        ]);

        $base = Base::findOrFail($data['base_id']);

        try {
            $rows = $opening->parseStockCsv($data['file']->getRealPath());
            $tires = $opening->importStock($rows, $base, $request->user(), $data['as_of']);
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('starting-point.index')
            ->with('success', count($tires).' cubiertas ingresadas al stock de '.$base->name.' (punto de partida).');
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
