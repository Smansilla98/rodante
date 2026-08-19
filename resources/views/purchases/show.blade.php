@extends('layouts.app')
@section('kicker', 'Compra')
@section('title', $purchase->number)
@section('content')
<x-page-header kicker="Compra" :title="$purchase->number" :subtitle="$purchase->supplier->name.' · '.$purchase->purchased_at->format('d/m/Y')" :crumbs="[
    ['label' => 'Tablero', 'url' => route('dashboard')],
    ['label' => 'Compras', 'url' => route('purchases.index')],
    ['label' => $purchase->number],
]">
    <x-slot:actions>
        <a href="{{ route('purchases.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Compras</a>
        @if(auth()->user()->role->canManageAbm() && ! $purchase->isConfirmed())
            @if(request('edit'))
                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-ghost">Cancelar</a>
            @else
                <a href="{{ route('purchases.show', [$purchase, 'edit' => 1]) }}" class="btn btn-dark">Editar</a>
            @endif
            <x-abm-delete :action="route('purchases.destroy', $purchase)" confirm="¿Anular este borrador?" label="Anular" />
        @endif
    </x-slot:actions>
</x-page-header>

<x-panel>
    @if(auth()->user()->role->canManageAbm() && ! $purchase->isConfirmed() && request('edit'))
        <form method="POST" action="{{ route('purchases.update', $purchase) }}" class="grid md:grid-cols-3 gap-4 mb-6">
            @csrf
            @method('PUT')
            <label class="field"><span>Proveedor</span>
                <select name="supplier_id" required>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($purchase->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span>Base</span>
                <select name="base_id" required>
                    @foreach($bases as $base)
                        <option value="{{ $base->id }}" @selected($purchase->base_id === $base->id)>{{ $base->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span>Fecha</span>
                <input type="date" name="purchased_at" required value="{{ $purchase->purchased_at->toDateString() }}">
            </label>
            <label class="field md:col-span-3"><span>Notas</span><textarea name="notes" rows="2">{{ $purchase->notes }}</textarea></label>
            <div><button class="btn btn-dark btn-sm">Guardar datos</button></div>
        </form>
    @else
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div>{{ $purchase->base->name }}</div>
            <x-status :tone="$purchase->isConfirmed() ? 'green' : 'amber'">
                {{ $purchase->isConfirmed() ? 'Confirmada' : 'Borrador' }}
            </x-status>
        </div>
    @endif
    <x-content-table>
        <thead>
            <tr>
                <th scope="col">Marca</th>
                <th scope="col">Modelo</th>
                <th scope="col">Medida</th>
                <th scope="col">Cantidad</th>
                <th scope="col">Números</th>
                <th scope="col">Cubiertas</th>
            </tr>
        </thead>
        <tbody>
        @forelse($purchase->items as $item)
            <tr>
                <td>{{ $item->brand->name }}</td>
                <td class="mono">{{ $item->model->code }}</td>
                <td>{{ $item->size->displayName() }}</td>
                <td class="mono">{{ $item->quantity }}</td>
                <td class="mono">
                    @if($item->first_number)
                        Nº {{ $item->first_number }} a {{ $item->last_number }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    <div class="flex flex-wrap gap-2">
                        @foreach($item->tires as $tire)
                            <a class="chip-link" href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                        @endforeach
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty title="Sin ítems" /></td></tr>
        @endforelse
        </tbody>
    </x-content-table>
    @if(! $purchase->isConfirmed() && auth()->user()->role->canWrite())
        <form method="POST" action="{{ route('purchases.confirm', $purchase) }}" class="mt-6" data-confirm="Al confirmar, las cubiertas ingresan a stock con su número individual. El borrador deja de poder anularse. ¿Confirmar la compra?">
            @csrf
            <p class="hint mb-3">Confirmá solo si los números y la medida están bien. Después las cubiertas quedan en stock listas para instalar.</p>
            <button class="btn btn-primary">Confirmar e ingresar a stock</button>
        </form>
    @endif
</x-panel>
@endsection
