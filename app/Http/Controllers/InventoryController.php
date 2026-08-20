<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Models\Base;
use App\Models\InventorySession;
use App\Services\InventoryService;
use App\Support\AccessScope;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', InventorySession::class);
        $query = InventorySession::with('base', 'opener')->latest('opened_at');
        AccessScope::inventorySessions($query, $request->user());

        return view('inventories.index', [
            'sessions' => $query->paginate(30),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', InventorySession::class);
        $bases = Base::query()->where('is_active', true)->orderBy('name');
        AccessScope::applyCompany($bases, $request->user());
        if (! AccessScope::seesEverything($request->user())) {
            $ids = AccessScope::visibleBaseIds($request->user());
            $bases->whereIn('id', $ids ?: [0]);
        }

        return view('inventories.create', [
            'bases' => $bases->get(),
        ]);
    }

    public function store(Request $request, InventoryService $inventories)
    {
        $this->authorize('create', InventorySession::class);
        $data = $request->validate([
            'base_id' => 'required|exists:bases,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $session = $inventories->open(
                $request->user(),
                Base::findOrFail($data['base_id']),
                $data['notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()->route('inventories.show', $session)
            ->with('success', 'Inventario '.$session->number.' abierto. Snapshot: '.$session->expected_count.' cubiertas.');
    }

    public function show(Request $request, InventorySession $inventory)
    {
        $this->authorizeVisible('view', $inventory);
        $inventory->load(['base', 'opener', 'closer', 'approver']);
        $lines = $inventory->lines()
            ->with(['tire.brand', 'tire.model', 'expectedBase', 'observedBase', 'scanner'])
            ->orderByRaw("CASE delta
                WHEN 'MISSING' THEN 1
                WHEN 'WRONG_BASE' THEN 2
                WHEN 'UNEXPECTED' THEN 3
                WHEN 'MOUNTED' THEN 4
                WHEN 'OK' THEN 5
                ELSE 6 END")
            ->orderBy('id')
            ->paginate(80);

        return view('inventories.show', [
            'session' => $inventory,
            'lines' => $lines,
        ]);
    }

    public function start(Request $request, InventorySession $inventory, InventoryService $inventories)
    {
        $this->authorize('count', $inventory);
        try {
            $inventories->startCounting($inventory, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Conteo iniciado. Escaneá o ingresá el número de cada cubierta.');
    }

    public function scan(Request $request, InventorySession $inventory, InventoryService $inventories)
    {
        $this->authorize('count', $inventory);
        $data = $request->validate([
            'q' => 'required|string|max:80',
        ]);

        try {
            $line = $inventories->scan($inventory, $request->user(), $data['q']);
        } catch (DomainException $e) {
            return back()->withErrors(['q' => $e->getMessage()])->withInput();
        }

        $tire = $line->tire;

        return back()->with('success', $tire->displayName().' · '.$line->delta?->label());
    }

    public function review(Request $request, InventorySession $inventory, InventoryService $inventories)
    {
        $this->authorize('count', $inventory);
        try {
            $inventories->submitForReview($inventory, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Enviado a revisión. Revisá faltantes y sobrantes antes de cerrar.');
    }

    public function close(Request $request, InventorySession $inventory, InventoryService $inventories)
    {
        $this->authorize('close', $inventory);
        $data = $request->validate([
            'apply_fixes' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        $apply = (bool) ($data['apply_fixes'] ?? false);
        if ($apply) {
            $this->authorize('adjust', $inventory);
        }

        try {
            $inventories->close($inventory, $request->user(), $apply, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $msg = $apply
            ? 'Inventario cerrado. Se aplicaron correcciones de base donde correspondía.'
            : 'Inventario cerrado. Las diferencias quedaron auditadas sin mover ubicaciones.';

        return redirect()->route('inventories.show', $inventory)->with('success', $msg);
    }

    public function cancel(Request $request, InventorySession $inventory, InventoryService $inventories)
    {
        $this->authorize('cancel', $inventory);
        $data = $request->validate(['notes' => 'nullable|string|max:1000']);
        try {
            $inventories->cancel($inventory, $request->user(), $data['notes'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('inventories.index')->with('success', 'Inventario cancelado.');
    }
}
