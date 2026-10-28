@extends('layouts.app')
@section('title', 'Registrar transferencia')
@section('content')
<h1>Registrar transferencia interna</h1>
<p class="text-secondary">Mueve fondos entre tus cuentas activas. No se contabiliza como ingreso, gasto ni consumo de presupuesto.</p>
<form class="card p-4 mx-auto" style="max-width:760px" method="POST" action="{{ route('transferencias.store') }}" data-submit-form data-confirm="La transferencia no se podrá editar ni eliminar. ¿Confirmar el registro?">
    @csrf
    <input type="hidden" name="clave_idempotencia" value="{{ old('clave_idempotencia', $clave) }}">
    <div class="alert alert-info">Las transferencias registradas son inmutables: no se pueden editar ni eliminar. Comprueba las cuentas, el importe y la fecha antes de confirmar. Los reenvíos de este formulario con los mismos datos no duplican el registro.</div>
    @if($cuentas->where('archivada', false)->count() < 2)<p class="alert alert-warning">Se necesitan al menos dos cuentas activas. <a href="{{ route('cuentas.create') }}">Crear cuenta</a></p>@endif
    @foreach(['origen_id' => 'Cuenta origen', 'destino_id' => 'Cuenta destino'] as $field => $label)
    <div class="mb-3"><label class="form-label" for="{{ $field }}">{{ $label }}</label><select class="form-select" id="{{ $field }}" name="{{ $field }}" required><option value="">Selecciona una cuenta</option>@foreach($cuentas->where('archivada', false) as $account)<option value="{{ $account->id }}" @selected(old($field) == $account->id)>{{ $account->nombre }} · {{ \App\Support\Money::format($account->saldo) }}</option>@endforeach</select></div>
    @endforeach
    <div class="mb-3"><label class="form-label" for="importe">Importe en MXN</label><input class="form-control" name="importe" id="importe" inputmode="decimal" maxlength="16" value="{{ old('importe') }}" required><div class="form-text">Mayor que cero, sin comas y con máximo dos decimales.</div></div>
    <div class="mb-3"><label class="form-label" for="fecha">Fecha</label><input class="form-control" type="date" name="fecha" id="fecha" value="{{ old('fecha', $hoy) }}" max="{{ $hoy }}" required></div>
    <div class="mb-4"><label class="form-label" for="descripcion">Descripción (opcional)</label><textarea class="form-control" name="descripcion" id="descripcion" maxlength="500">{{ old('descripcion') }}</textarea></div>
    <div class="d-flex flex-wrap gap-3"><button class="btn btn-primary" type="submit" data-loading-text="Registrando…">Confirmar transferencia</button><a class="btn btn-outline-primary" href="{{ route('transferencias.index') }}">Volver</a></div>
</form>
@endsection
