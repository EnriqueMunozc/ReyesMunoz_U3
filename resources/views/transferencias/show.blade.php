@extends('layouts.app')
@section('title', 'Consultar transferencia')
@section('content')
<h1>Detalle de la transferencia</h1>
<section class="card p-4"><dl class="row mb-0">
<dt class="col-sm-4">Cuenta origen</dt><dd class="col-sm-8">{{ $item->origen->nombre }}</dd><dt class="col-sm-4">Cuenta destino</dt><dd class="col-sm-8">{{ $item->destino->nombre }}</dd><dt class="col-sm-4">Importe</dt><dd class="col-sm-8">{{ \App\Support\Money::format($item->importe) }}</dd><dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ $item->fecha->format('d/m/Y') }}</dd><dt class="col-sm-4">Descripción</dt><dd class="col-sm-8 text-break">{{ $item->descripcion ?: 'Sin descripción.' }}</dd></dl>
<p class="alert alert-info mt-3">Esta transferencia es inmutable y no cuenta como ingreso ni gasto. Su historial se conserva al archivar las cuentas.</p><a href="{{ route('transferencias.index') }}">Volver al historial</a></section>
@endsection
