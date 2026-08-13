@extends('layouts.app')
@section('kicker', 'Compra')
@section('title', $purchase->number)
@section('content')
<x-page-header kicker="Compra" :title="$purchase->number" :subtitle="$purchase->supplier->name.' · '.$purchase->purchased_at->format('d/m/Y')">
    <x-slot:actions>
        <a href="{{ route('purchases.index') }}" class="btn btn-ghost"><x-icon name="back" class="w-4 h-4" /> Compras</a>
    </x-slot:actions>
</x-page-header>

<x-panel>
    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div>{{ $purchase->base->name }}</div>
        <x-status :tone="$purchase->isConfirmed() ? 'green' : 'amber'">
            {{ $purchase->isConfirmed() ? 'Confirmada' : 'Borrador' }}
        </x-status>
    </div>
    @foreach($purchase->items as $item)
        <div class="border-t border-slate-100 pt-4 mt-4">
            <div class="font-semibold">{{ $item->brand->name }} {{ $item->model->code }} {{ $item->size->displayName() }} × {{ $item->quantity }}</div>
            @if($item->first_number)
                <div class="text-sm text-slate-500">Nº {{ $item->first_number }} a {{ $item->last_number }}</div>
            @endif
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach($item->tires as $tire)
                    <a class="px-2 py-1 bg-slate-100 rounded-md text-sm font-medium" href="{{ route('tires.show', $tire) }}">{{ $tire->displayName() }}</a>
                @endforeach
            </div>
        </div>
    @endforeach
    @if(! $purchase->isConfirmed())
        <form method="POST" action="{{ route('purchases.confirm', $purchase) }}" class="mt-6">
            @csrf
            <button class="btn btn-primary">Confirmar e ingresar a stock</button>
        </form>
    @endif
</x-panel>
@endsection
