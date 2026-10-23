@extends('layouts.app')
@section('title', ($item ? 'Editar ' : 'Crear ').['cuentas'=>'cuenta','categorias'=>'categoría','movimientos'=>'movimiento'][$resource])
@section('content')
@php
    $title = ['cuentas' => 'cuenta', 'categorias' => 'categoría', 'movimientos' => 'movimiento'][$resource];
    $locked = $resource === 'cuentas' && $item && $item->movimientos()->exists();
@endphp
<div class="d-flex justify-content-between align-items-center gap-3 mb-4"><div><span class="eyebrow">TUS FINANZAS</span><h1 class="mt-2">{{ $item ? 'Editar' : 'Crear' }} {{ $title }}</h1></div><a href="{{ route($resource.'.index') }}">Volver al listado</a></div>
<form class="card p-4 p-md-5 mx-auto" style="max-width:760px" method="POST" action="{{ $item ? route($resource.'.update', $item->id) : route($resource.'.store') }}" data-submit-form>
    @csrf
    @if($item) @method('PUT') @endif
    @if($resource !== 'movimientos')
        <div class="mb-3"><label class="form-label" for="nombre">Nombre</label><input class="form-control" name="nombre" id="nombre" value="{{ old('nombre', $item?->nombre) }}" maxlength="100" required></div>
    @else
        @if($cuentas->where('archivada', false)->isEmpty() || $categorias->isEmpty())
            <div class="alert alert-info">Para registrar un movimiento necesitas una cuenta activa y una categoría del tipo correspondiente. <a href="{{ route('cuentas.create') }}">Crear cuenta</a> · <a href="{{ route('categorias.create') }}">Crear categoría</a></div>
        @endif
        <div class="mb-3"><label class="form-label" for="cuenta_id">Cuenta</label><select id="cuenta_id" name="cuenta_id" class="form-select" required><option value="">Selecciona una cuenta</option>@foreach($cuentas as $account) @if(!$account->archivada || $item?->cuenta_id === $account->id)<option value="{{ $account->id }}" @selected(old('cuenta_id', $item?->cuenta_id) == $account->id)>{{ $account->nombre }}{{ $account->archivada ? ' (archivada: reactívala para editar)' : '' }}</option>@endif @endforeach</select></div>
    @endif
    <div class="mb-3"><label class="form-label" for="tipo">Tipo</label><select class="form-select" id="tipo" name="tipo" required>
        @foreach($resource === 'cuentas' ? \App\Services\FinanceService::ACCOUNT_TYPES : ['ingreso','gasto'] as $type)<option value="{{ $type }}" @selected(old('tipo', $item?->tipo ?? ($resource === 'cuentas' ? 'efectivo' : 'gasto')) === $type)>{{ ucfirst($type) }}</option>@endforeach
    </select></div>
    @if($resource === 'movimientos')
        <div class="mb-3"><label class="form-label" for="categoria_id">Categoría</label><select class="form-select" id="categoria_id" name="categoria_id" required><option value="">Selecciona una categoría</option>@foreach($categorias as $category)<option value="{{ $category->id }}" data-tipo="{{ $category->tipo }}" @selected(old('categoria_id', $item?->categoria_id) == $category->id)>{{ $category->nombre }} ({{ $category->tipo }})</option>@endforeach</select><div class="form-text">La categoría debe coincidir con el tipo de movimiento.</div></div>
        <div class="mb-3"><label class="form-label" for="importe">Importe en MXN</label><input class="form-control" id="importe" name="importe" inputmode="decimal" value="{{ old('importe', $item?->importe) }}" pattern="(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?" required><div class="form-text">Mayor que cero, sin comas y con máximo dos decimales. Ejemplo: 125.50</div></div>
        <div class="mb-3"><label class="form-label" for="fecha">Fecha</label><input type="date" class="form-control" id="fecha" name="fecha" max="{{ $hoy }}" value="{{ old('fecha', $item?->fecha?->toDateString() ?? $hoy) }}" required></div>
        <div class="mb-4"><label class="form-label" for="descripcion">Descripción (opcional)</label><textarea class="form-control" id="descripcion" name="descripcion" maxlength="500" rows="3">{{ old('descripcion', $item?->descripcion) }}</textarea></div>
    @elseif($resource === 'cuentas')
        @if($locked)<p class="alert alert-info">El saldo y la fecha inicial están bloqueados porque esta cuenta ya tiene movimientos. Puedes editar su nombre, tipo o estado sin cambiar el historial.</p>@endif
        <div class="mb-3"><label class="form-label" for="saldo_inicial">Saldo inicial en MXN</label><input class="form-control" id="saldo_inicial" name="saldo_inicial" inputmode="decimal" value="{{ old('saldo_inicial', $item?->saldo_inicial ?? '0.00') }}" required @readonly($locked)><div class="form-text">Puede ser cero. No se contabiliza como ingreso mensual.</div></div>
        <div class="mb-4"><label class="form-label" for="fecha_inicial">Fecha inicial</label><input class="form-control" type="date" name="fecha_inicial" id="fecha_inicial" value="{{ old('fecha_inicial', $item?->fecha_inicial?->toDateString() ?? $hoy) }}" max="{{ $hoy }}" required @readonly($locked)></div>
        @if($item)<input type="hidden" name="archivada" value="0"><div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="archivada" id="archivada" value="1" @checked(old('archivada', $item->archivada))><label class="form-check-label" for="archivada">Cuenta archivada</label><div class="form-text">Conserva el historial; no acepta nuevos movimientos ni forma parte del saldo total activo. Desmarca para reactivarla.</div></div>@endif
    @else
        <p class="text-secondary small">Si tiene movimientos, podrás cambiar su nombre, pero no su tipo ni eliminarla.</p>
    @endif
    <button class="btn btn-primary align-self-start" type="submit" data-loading-text="Guardando…">Guardar {{ $title }}</button>
</form>
@endsection
