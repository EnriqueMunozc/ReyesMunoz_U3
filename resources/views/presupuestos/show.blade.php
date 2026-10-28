@extends('layouts.app')
@section('title', 'Consultar presupuesto')
@section('content')
<h1>{{ $item->categoria->nombre }} · {{ $item->mes }}</h1>
<section class="card p-4">@include('presupuestos.indicators')<p class="text-secondary mt-3">El consumo incluye gastos de cuentas archivadas. Excluye transferencias y saldos iniciales.</p><div class="d-flex flex-wrap gap-3"><a class="btn btn-primary" href="{{ route('presupuestos.edit', $item->id) }}">Editar</a><form method="POST" action="{{ route('presupuestos.destroy', $item->id) }}" data-confirm="¿Eliminar este presupuesto? Los gastos se conservarán." data-submit-form>@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit" data-loading-text="Eliminando…">Eliminar presupuesto</button></form><a href="{{ route('presupuestos.index') }}">Volver al listado</a></div></section>
@endsection
