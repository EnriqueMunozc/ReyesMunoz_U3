@extends('layouts.app')
@section('title', 'Espera un momento')
@section('content')
    <section class="card p-4 p-md-5 mx-auto error-card" role="alert">
        <span class="eyebrow">UNA PAUSA POR SEGURIDAD</span>
        <h1 class="mt-3">Demasiados intentos</h1>
        <p class="text-secondary">Espera {{ $seconds ?? 60 }} segundos antes de volver a iniciar sesión.</p>
        <a class="btn btn-primary align-self-start" href="{{ route('login') }}">Volver al inicio de sesión</a>
    </section>
@endsection
