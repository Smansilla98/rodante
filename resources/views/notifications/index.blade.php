@extends('layouts.app')
@section('title', 'Avisos')
@section('content')
<x-page-header kicker="Consulta" title="Avisos" subtitle="Eventos de tu empresa. No se mezclan con otras." />
<x-panel>
    <ul class="space-y-3">
        @forelse($notifications as $n)
            <li>
                <form method="POST" action="{{ route('notifications.read', $n->id) }}">
                    @csrf
                    <button class="btn btn-ghost w-full text-left {{ $n->read_at ? 'opacity-70' : '' }}">
                        <strong>{{ $n->data['title'] ?? 'Aviso' }}</strong>
                        <span class="block text-sm">{{ $n->data['body'] ?? '' }}</span>
                        <span class="block mono text-xs">{{ $n->created_at->format('d/m/Y H:i') }}</span>
                    </button>
                </form>
            </li>
        @empty
            <x-empty title="No hay avisos" text="Cuando se abra o cierre una OT, aparece acá." />
        @endforelse
    </ul>
</x-panel>
@endsection
