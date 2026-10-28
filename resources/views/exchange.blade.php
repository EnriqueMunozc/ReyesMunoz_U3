@extends('layouts.app')
@section('title', 'Conversor USD/MXN')
@section('content')
<div class="mb-4"><span class="eyebrow">TIPO DE CAMBIO DE REFERENCIA</span><h1 class="mt-2">Conversor USD/MXN</h1><p class="text-secondary">La conversión es informativa. No registra movimientos ni modifica los saldos de tus cuentas.</p></div>
<form method="GET" action="{{ route('conversor') }}" class="card p-4 mb-4">
    <div class="row g-3">
        @foreach(['base' => 'Moneda de origen', 'destino' => 'Moneda de destino'] as $field => $label)
        <div class="col-sm-6 col-lg-4"><label class="form-label" for="{{ $field }}">{{ $label }}</label><select class="form-select" name="{{ $field }}" id="{{ $field }}" required>@foreach(['USD' => 'USD · Dólar estadounidense', 'MXN' => 'MXN · Peso mexicano'] as $currency => $name)<option value="{{ $currency }}" @selected(($input[$field] ?? '') === $currency)>{{ $name }}</option>@endforeach</select></div>
        @endforeach
        <div class="col-lg-4"><label class="form-label" for="importe">Importe (opcional)</label><input class="form-control" name="importe" id="importe" inputmode="decimal" value="{{ $input['importe'] ?? '' }}" maxlength="12"><div class="form-text">Hasta 999999999.99, sin comas y con dos decimales como máximo. Deja vacío para consultar solo la tasa.</div></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Consultar y convertir</button></div>
    </div>
</form>
@if($error)<div class="alert alert-warning" role="alert">{{ $error }} Las demás funciones de Cuentahorro siguen disponibles.</div>@endif
@if($data)
<section class="card p-4 p-md-5">
    <h2 class="h4">{{ ['consulta' => 'Tasa recién consultada', 'cache' => 'Tasa en caché vigente', 'respaldo' => 'Tasa de respaldo por falla del proveedor'][$data['estado']] }}</h2>
    <p class="{{ $data['estado'] === 'respaldo' ? 'alert alert-warning' : 'text-secondary' }}">{{ $data['aviso'] }}</p>
    @if($data['resultado'] !== null)<p class="h3 text-break my-3">{{ $data['importe'] }} {{ $data['base'] }} = {{ $data['resultado'] }} {{ $data['destino'] }}</p>@endif
    <dl class="row mb-0"><dt class="col-sm-4">Tasa aplicada</dt><dd class="col-sm-8 text-break">1 {{ $data['base'] }} = {{ $data['tasa'] }} {{ $data['destino'] }}</dd><dt class="col-sm-4">Proveedor</dt><dd class="col-sm-8">{{ $data['proveedor'] }} · tasa combinada de referencia</dd><dt class="col-sm-4">Fecha de referencia</dt><dd class="col-sm-8">{{ $data['fecha_referencia'] }}</dd><dt class="col-sm-4">Consulta al proveedor (UTC)</dt><dd class="col-sm-8 text-break">{{ $data['consultado_en'] }}</dd><dt class="col-sm-4">Redondeo del resultado</dt><dd class="col-sm-8">Dos decimales; una mitad exacta se redondea hacia arriba.</dd></dl>
</section>
@endif
@endsection
