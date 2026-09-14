<x-mail::message>
# Informe semanal de neumáticos

@if($recipientName)
Hola {{ $recipientName }},
@endif

Período **{{ $report['from']->format('d/m/Y') }}** al **{{ $report['to']->format('d/m/Y') }}**.  
Redactado el {{ $report['generated_at']->format('d/m/Y H:i') }}.

## Stock al momento del informe

| Estado | Cantidad |
|:-------|--------:|
@foreach($report['stock_counts'] as $row)
| {{ $row['status'] }} | {{ $row['total'] }} |
@endforeach

**En stock listo para instalar:** {{ $report['stock_total'] }}

@if($report['stock_tires']->isNotEmpty())
| Nº | Cubierta | Base |
|:---|:---------|:-----|
@foreach($report['stock_tires']->take(80) as $tire)
| {{ $tire->individual_number }} | {{ $tire->displayName() }} | {{ $tire->currentLocation?->base?->name ?? '—' }} |
@endforeach
@if($report['stock_tires']->count() > 80)
| … | +{{ $report['stock_tires']->count() - 80 }} más | |
@endif
@endif

## Movimientos de la semana

@if(count($report['movements']) === 0)
No hubo movimientos en el período.
@else
| Momento | Cubierta | Salió | Entró | Unidad | Tipo |
|:--------|:---------|:------|:------|:-------|:-----|
@foreach($report['movements'] as $row)
| {{ $row['moment'] }} | {{ $row['tire'] }} | {{ $row['left'] }} | {{ $row['entered'] }} | {{ $row['unit'] }} | {{ $row['type'] }} |
@endforeach
@endif

@if($report['purchases']->isNotEmpty())
## Compras

| Fecha | Nº OC | Proveedor | Base | Cubiertas |
|:------|:------|:----------|:-----|--------:|
@foreach($report['purchases'] as $purchase)
| {{ ($purchase->confirmed_at ?? $purchase->purchased_at)?->format('d/m/Y') ?? '—' }} | {{ $purchase->number ?? '#'.$purchase->id }} | {{ $purchase->supplier?->name ?? '—' }} | {{ $purchase->base?->name ?? '—' }} | {{ $purchase->items->sum('quantity') }} |
@endforeach
@endif

@if(count($report['retirements']) > 0)
## Bajas

| Momento | Cubierta | Motivo | Notas |
|:--------|:---------|:-------|:------|
@foreach($report['retirements'] as $row)
| {{ $row['moment'] }} | {{ $row['tire'] }} | {{ $row['reason'] ?? '—' }} | {{ $row['notes'] ?? '—' }} |
@endforeach
@endif

<x-mail::button :url="route('reports.weekly', ['from' => $report['from']->toDateString(), 'to' => $report['to']->toDateString()])">
Ver en Rodante
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
