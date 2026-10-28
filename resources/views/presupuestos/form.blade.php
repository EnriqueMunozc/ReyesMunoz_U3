@extends('layouts.app')
@section('title', $item ? 'Editar presupuesto' : 'Crear presupuesto')
@section('content')
<h1>{{ $item ? 'Editar' : 'Crear' }} presupuesto mensual</h1>
<p class="text-secondary">El presupuesto es informativo: superar el límite no bloquea un gasto con saldo suficiente.</p>
<form class="card p-4 mx-auto" style="max-width:760px" method="POST" action="{{ $item ? route('presupuestos.update', $item->id) : route('presupuestos.store') }}" data-submit-form>
@csrf @if($item) @method('PUT') @endif
@if($categorias->isEmpty())<p class="alert alert-info">Crea primero una <a href="{{ route('categorias.create') }}">categoría de gasto</a>.</p>@endif
<div class="mb-3"><label for="categoria_id" class="form-label">Categoría de gasto</label><select id="categoria_id" name="categoria_id" class="form-select" required><option value="">Selecciona una categoría</option>@foreach($categorias as $category)<option value="{{ $category->id }}" @selected(old('categoria_id', $item?->categoria_id) == $category->id)>{{ $category->nombre }}</option>@endforeach</select></div>
<div class="mb-3"><label for="mes" class="form-label">Mes</label><input class="form-control" type="month" id="mes" name="mes" min="1900-01" max="2199-12" value="{{ old('mes', $item?->mes ?? now(config('app.business_timezone'))->format('Y-m')) }}" required></div>
<div class="mb-4"><label for="limite" class="form-label">Límite en MXN</label><input class="form-control" id="limite" name="limite" inputmode="decimal" maxlength="16" value="{{ old('limite', $item?->limite) }}" required><div class="form-text">Mayor que cero, sin comas y con máximo dos decimales.</div></div>
<div class="d-flex flex-wrap gap-3"><button class="btn btn-primary" type="submit" data-loading-text="Guardando…">Guardar presupuesto</button><a href="{{ route('presupuestos.index') }}">Volver</a></div>
</form>
@endsection
