@extends('layouts.app')
@section('title', 'Inicio')

@section('content')
    <section class="dashboard-heading d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mb-lg-5">
        <div>
            <span class="eyebrow">TU ESPACIO PERSONAL</span>
            <h1 class="mt-3">Hola, {{ auth()->user()->name }}<span class="welcome-dot">.</span></h1>
            <p class="text-secondary mb-0">Qué bueno tenerte en Cuentahorro.</p>
        </div>
        <span class="badge currency-badge mt-2">MXN · Peso mexicano</span>
    </section>
    <section class="empty-state card text-center p-4 p-md-5" aria-labelledby="empty-title">
        <div class="empty-icon mx-auto mb-4" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="8" y="14" width="32" height="25" rx="5"/><path d="M10 15V12a4 4 0 0 1 4-4h20v6M40 23H30a4 4 0 0 0 0 8h10"/><circle cx="31" cy="27" r="1" fill="currentColor" stroke="none"/></svg>
        </div>
        <span class="eyebrow">TODO EMPIEZA POR AQUÍ</span>
        <h2 class="mt-3" id="empty-title">Tu espacio está listo</h2>
        <p class="text-secondary mx-auto empty-copy mt-2">Todavía no tienes cuentas ni movimientos registrados. Aquí podrás empezar a construir una visión más clara de tus finanzas.</p>
        <p class="phase-note mx-auto mb-0 mt-3">El registro de cuentas y movimientos estará disponible en la siguiente etapa. Por ahora puedes acceder a tu cuenta y cerrar sesión de forma segura.</p>
    </section>
@endsection
