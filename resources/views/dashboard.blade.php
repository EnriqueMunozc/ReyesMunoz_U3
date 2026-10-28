@extends('layouts.app')
@section('title', 'Inicio')
@section('content')
<section class="dashboard-heading d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div><span class="eyebrow">TU ESPACIO PERSONAL</span><h1 class="mt-3">Hola, {{ auth()->user()->name }}<span class="welcome-dot">.</span></h1><p class="text-secondary">Tu resumen de {{ $mes }} · America/Mexico_City</p></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="{{ route('cuentas.create') }}">Crear cuenta</a><a class="btn btn-primary" href="{{ route('movimientos.create') }}">Registrar movimiento</a></div>
</section>
<div class="d-flex flex-wrap gap-3 mb-4"><a href="{{ route('transferencias.create') }}">Registrar transferencia interna</a><a href="{{ route('transferencias.index') }}">Consultar transferencias</a></div>
@if($cuentas->isEmpty())
<section class="card p-5 text-center mb-4"><span class="eyebrow">TODO EMPIEZA POR AQUÍ</span><h2 class="mt-3">Tu espacio está listo</h2><p class="text-secondary">Aún no tienes cuentas. Crea la primera con su saldo y fecha inicial; después registra tus categorías, ingresos y gastos.</p><a class="btn btn-primary align-self-center" href="{{ route('cuentas.create') }}">Crear mi primera cuenta</a></section>
@else
<div class="row g-3 mb-3">
@foreach(['saldo_total'=>'Saldo total activo','ingresos_mes'=>'Ingresos del mes','gastos_mes'=>'Gastos del mes','balance_mes'=>'Balance mensual'] as $key=>$label)
<div class="col-sm-6 col-xl-3"><section class="card p-4 h-100"><h2 class="h6 text-secondary">{{ $label }}</h2><p class="h4 mb-0 mt-2 text-break">{{ \App\Support\Money::format($$key) }}</p></section></div>
@endforeach
</div>
<p class="text-secondary small mb-4">El saldo total incluye únicamente cuentas activas. Los ingresos, gastos y balance mensual conservan los movimientos de cuentas archivadas. El saldo inicial no cuenta como ingreso.</p>
<section class="card p-4 mb-4"><h2 class="h4 mb-3">Tus cuentas</h2><div class="row g-3">@foreach($cuentas as $account)<div class="col-md-6"><a href="{{ route('cuentas.show', $account->id) }}">{{ $account->nombre }}</a><span class="small text-secondary"> · {{ $account->archivada ? 'Archivada' : 'Activa' }}</span><p class="mb-0">{{ \App\Support\Money::format($account->saldo) }}</p></div>@endforeach</div></section>
@endif
<section class="card p-4 mb-4"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 class="h4 mb-0">Presupuestos de {{ $mes }}</h2><a href="{{ route('presupuestos.index', ['mes' => $mes]) }}">Ver todos ({{ $total_presupuestos_mes }})</a></div>
@if($presupuestos_mes->isEmpty())<p class="text-secondary">No hay presupuestos para este mes.</p><a href="{{ route('presupuestos.create') }}">Crear presupuesto</a>
@else<div class="row g-3">@foreach($presupuestos_mes as $item) @php($metrics = $item->indicadores)<div class="col-lg-6"><h3 class="h6"><a href="{{ route('presupuestos.show', $item->id) }}">{{ $item->categoria->nombre }}</a></h3><p class="mb-1">{{ \App\Support\Money::format($metrics['consumo']) }} de {{ \App\Support\Money::format($item->limite) }} · {{ $metrics['porcentaje_utilizado'] }} %</p><p class="small text-secondary mb-0">Disponible: {{ \App\Support\Money::format($metrics['disponible']) }} · Exceso: {{ \App\Support\Money::format($metrics['exceso']) }}</p></div>@endforeach</div>@endif
<p class="text-secondary small mt-3 mb-0">Los presupuestos incluyen gastos de cuentas archivadas. Las transferencias internas y los saldos iniciales no son ingresos, gastos ni consumo de presupuesto.</p></section>
<section class="card p-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h4 mb-0">Últimos movimientos</h2><a href="{{ route('movimientos.index') }}">Ver historial</a></div>
@if($ultimos_movimientos->isEmpty())<p class="text-secondary mb-0">Aún no hay movimientos. <a href="{{ route('categorias.create') }}">Crea una categoría</a> y registra tu primer ingreso o gasto.</p>
@else<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Cuenta / categoría</th><th>Tipo</th><th>Importe</th></tr></thead><tbody>@foreach($ultimos_movimientos as $movement)<tr><td>{{ $movement->fecha->format('d/m/Y') }}</td><td><a href="{{ route('movimientos.show', $movement->id) }}">{{ $movement->cuenta->nombre }} · {{ $movement->categoria->nombre }}</a></td><td>{{ ucfirst($movement->tipo) }}</td><td class="text-nowrap">{{ \App\Support\Money::format($movement->importe) }}</td></tr>@endforeach</tbody></table></div>@endif
</section>
@endsection
