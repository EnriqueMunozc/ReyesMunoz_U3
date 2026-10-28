@extends('layouts.app')
@section('title', 'Transferencias')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><h1>Transferencias internas</h1><a class="btn btn-primary" href="{{ route('transferencias.create') }}">Registrar transferencia</a></div>
<p class="text-secondary">El historial se conserva incluso si una cuenta está archivada. Las transferencias no se editan ni se eliminan.</p>
<form class="card p-3 mb-4" method="GET"><div class="row g-3">
    <div class="col-md-4"><label for="cuenta_id" class="form-label">Cuenta origen o destino</label><select id="cuenta_id" class="form-select" name="cuenta_id"><option value="">Todas</option>@foreach($cuentas as $account)<option value="{{ $account->id }}" @selected(request('cuenta_id') == $account->id)>{{ $account->nombre }}{{ $account->archivada ? ' (archivada)' : '' }}</option>@endforeach</select></div>
    @foreach(['desde' => 'Desde', 'hasta' => 'Hasta'] as $field => $label)<div class="col-md-4"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input type="date" class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ request($field) }}"></div>@endforeach
    <div class="col-12"><button class="btn btn-primary">Aplicar filtros</button> <a href="{{ route('transferencias.index') }}">Limpiar</a></div>
</div></form>
@if($items->isEmpty())<section class="card p-4"><h2 class="h4">No hay transferencias para mostrar</h2><p class="mb-0">Registra tu primera transferencia o ajusta los filtros.</p></section>
@else<div class="card p-3 table-responsive"><table class="table align-middle"><thead><tr><th>Fecha</th><th>Origen</th><th>Destino</th><th>Importe</th><th>Detalle</th></tr></thead><tbody>@foreach($items as $item)<tr><td>{{ $item->fecha->format('d/m/Y') }}</td><td>{{ $item->origen->nombre }}</td><td>{{ $item->destino->nombre }}</td><td class="text-nowrap">{{ \App\Support\Money::format($item->importe) }}</td><td><a href="{{ route('transferencias.show', $item->id) }}">Consultar</a></td></tr>@endforeach</tbody></table></div><div class="mt-3">{{ $items->links() }}</div>@endif
@endsection
