@extends('layouts.app')
@section('title', 'Consultar '.['cuentas'=>'cuenta','categorias'=>'categoría','movimientos'=>'movimiento'][$resource])
@section('content')
@php($used = $resource === 'cuentas' ? $item->tieneHistorial() : ($resource === 'categorias' && ($item->movimientos()->exists() || $item->presupuestos()->exists())))
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><h1>{{ $resource === 'movimientos' ? 'Detalle del movimiento' : $item->nombre }}</h1><a href="{{ route($resource.'.index') }}">Volver al listado</a></div>
<section class="card p-4 p-md-5">
@if($resource === 'cuentas')<p><a href="{{ route('movimientos.index', ['cuenta_id' => $item->id]) }}">Consultar movimientos</a> · <a href="{{ route('transferencias.index', ['cuenta_id' => $item->id]) }}">Consultar transferencias</a></p>@endif
<dl class="row mb-4">
<dt class="col-sm-4">Tipo</dt><dd class="col-sm-8">{{ ucfirst($item->tipo) }}</dd>
@if($resource === 'cuentas')
<dt class="col-sm-4">Saldo inicial</dt><dd class="col-sm-8">{{ \App\Support\Money::format($item->saldo_inicial) }}</dd><dt class="col-sm-4">Fecha inicial</dt><dd class="col-sm-8">{{ $item->fecha_inicial->format('d/m/Y') }}</dd><dt class="col-sm-4">Saldo actual</dt><dd class="col-sm-8">{{ \App\Support\Money::format($item->saldo) }}</dd><dt class="col-sm-4">Estado</dt><dd class="col-sm-8">{{ $item->archivada ? 'Archivada' : 'Activa' }}</dd>
@elseif($resource === 'movimientos')
<dt class="col-sm-4">Cuenta</dt><dd class="col-sm-8">{{ $item->cuenta->nombre }}</dd><dt class="col-sm-4">Categoría</dt><dd class="col-sm-8">{{ $item->categoria->nombre }}</dd><dt class="col-sm-4">Importe</dt><dd class="col-sm-8">{{ \App\Support\Money::format($item->importe) }}</dd><dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ $item->fecha->format('d/m/Y') }}</dd><dt class="col-sm-4">Descripción</dt><dd class="col-sm-8" style="white-space:pre-wrap">{{ $item->descripcion ?: 'Sin descripción.' }}</dd>
@endif
</dl>
<div class="d-flex flex-wrap gap-3"><a class="btn btn-primary" href="{{ route($resource.'.edit', $item->id) }}">Editar</a>
@if(!($resource === 'categorias' && $used) && !($resource === 'cuentas' && $used && $item->archivada))
<form method="POST" action="{{ route($resource.'.destroy', $item->id) }}" data-confirm="{{ $resource === 'cuentas' && $used ? '¿Archivar esta cuenta y conservar su historial?' : '¿Eliminar este registro? Esta acción no se puede deshacer.' }}" data-submit-form>@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit" data-loading-text="Procesando…">{{ $resource === 'cuentas' && $used ? 'Archivar cuenta' : 'Eliminar' }}</button></form>
@endif</div>
@if($resource === 'categorias' && $used)<p class="text-secondary small mt-3 mb-0">Esta categoría tiene movimientos o presupuestos y no se puede eliminar.</p>@endif
@if($resource === 'cuentas' && $used && $item->archivada)<p class="text-secondary small mt-3 mb-0">El historial se conserva. Puedes reactivar esta cuenta desde Editar.</p>@endif
</section>
@endsection
