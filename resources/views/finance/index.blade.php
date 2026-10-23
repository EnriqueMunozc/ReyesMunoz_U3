@extends('layouts.app')
@section('title', $resource === 'categorias' ? 'Categorías' : ucfirst($resource))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><span class="eyebrow">TU HISTORIAL, A TU RITMO</span><h1 class="mt-2">{{ $resource === 'categorias' ? 'Categorías' : ucfirst($resource) }}</h1></div><a class="btn btn-primary" href="{{ route($resource.'.create') }}">{{ ['cuentas'=>'Crear cuenta','categorias'=>'Crear categoría','movimientos'=>'Registrar movimiento'][$resource] }}</a></div>
@if($resource === 'movimientos')
<form method="GET" class="card p-3 mb-4"><div class="row g-3">
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="cuenta_id">Cuenta</label><select class="form-select" id="cuenta_id" name="cuenta_id"><option value="">Todas</option>@foreach($cuentas as $account)<option value="{{ $account->id }}" @selected(request('cuenta_id') == $account->id)>{{ $account->nombre }}{{ $account->archivada ? ' (archivada)' : '' }}</option>@endforeach</select></div>
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="categoria_id">Categoría</label><select class="form-select" id="categoria_id" name="categoria_id"><option value="">Todas</option>@foreach($categorias as $category)<option value="{{ $category->id }}" @selected(request('categoria_id') == $category->id)>{{ $category->nombre }} ({{ $category->tipo }})</option>@endforeach</select></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label" for="tipo">Tipo</label><select class="form-select" id="tipo" name="tipo"><option value="">Todos</option>@foreach(['ingreso','gasto'] as $type)<option value="{{ $type }}" @selected(request('tipo') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
    @foreach(['desde'=>'Desde','hasta'=>'Hasta'] as $field=>$label)<div class="col-sm-6 col-lg-2"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" type="date" id="{{ $field }}" name="{{ $field }}" value="{{ request($field) }}"></div>@endforeach
    <div class="col-12"><button class="btn btn-primary" type="submit">Aplicar filtros</button> <a class="btn btn-outline-primary" href="{{ route('movimientos.index') }}">Limpiar</a></div>
</div></form>
@endif
@if($items->isEmpty())
<section class="card p-5 text-center"><h2 class="h4">No hay {{ $resource === 'categorias' ? 'categorías' : $resource }} para mostrar</h2><p class="text-secondary mb-0">{{ $resource === 'movimientos' ? 'Registra tu primer ingreso o gasto, o ajusta los filtros.' : 'Usa el botón de arriba para crear tu primer registro.' }}</p></section>
@else
<div class="card p-2 p-md-3"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr>
@if($resource === 'movimientos')<th>Fecha</th><th>Cuenta / categoría</th><th>Tipo</th><th>Importe</th>@else<th>Nombre</th><th>Tipo</th>@if($resource === 'cuentas')<th>Saldo actual</th><th>Estado</th>@endif @endif<th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>
@foreach($items as $item)<tr>
@if($resource === 'movimientos')<td>{{ $item->fecha->format('d/m/Y') }}</td><td>{{ $item->cuenta->nombre }}<br><small class="text-secondary">{{ $item->categoria->nombre }}</small></td><td>{{ ucfirst($item->tipo) }}</td><td class="text-nowrap">{{ \App\Support\Money::format($item->importe) }}</td>
@else<td>{{ $item->nombre }}</td><td>{{ ucfirst($item->tipo) }}</td>@if($resource === 'cuentas')<td class="text-nowrap">{{ \App\Support\Money::format($item->saldo) }}</td><td>{{ $item->archivada ? 'Archivada' : 'Activa' }}</td>@endif @endif
<td class="text-end text-nowrap"><a href="{{ route($resource.'.show', $item->id) }}">Consultar</a> <a class="ms-3" href="{{ route($resource.'.edit', $item->id) }}">Editar</a></td></tr>@endforeach
</tbody></table></div></div>
<div class="mt-4">{{ $items->links() }}</div>
@endif
@endsection
