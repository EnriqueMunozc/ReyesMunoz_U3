@extends('layouts.app')
@section('title', 'Sesión vencida')
@section('content')
    <section class="card p-4 p-md-5 mx-auto error-card">
        <span class="eyebrow">VOLVAMOS A EMPEZAR</span>
        <h1 class="mt-3">La sesión del formulario venció</h1>
        <p class="text-secondary">Actualiza la página y vuelve a intentarlo. Por seguridad, tu solicitud no se procesó.</p>
        <a class="btn btn-primary align-self-start" href="{{ route('login') }}">Volver al inicio de sesión</a>
    </section>
@endsection
