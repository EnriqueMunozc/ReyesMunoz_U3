@extends('layouts.app')

@section('content')
    <div class="auth-shell row g-0">
        <aside class="auth-intro col-lg-5 d-flex flex-column justify-content-between p-4 p-xl-5">
            <div>
                <span class="eyebrow light">UN COMIENZO MÁS CLARO</span>
                <h1 class="display-5 mt-4">Dale un espacio<br class="d-none d-lg-block"> a tus finanzas.</h1>
                <p class="intro-copy mt-4">Cada hábito empieza con un primer paso. Este es el tuyo.</p>
            </div>
            <div class="intro-note mt-4 mt-lg-5">
                <span class="note-dot" aria-hidden="true"></span>
                <p class="mb-0">Un espacio personal.<br>Una visión más tranquila.</p>
            </div>
        </aside>
        <section class="col-lg-7 p-4 p-md-5">
            <div class="auth-form mx-auto">
                <span class="eyebrow">CUENTAHORRO</span>
                <h2 class="mt-3 mb-2">@yield('heading')</h2>
                <p class="text-secondary mb-4">@yield('description')</p>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert" tabindex="-1" data-error-summary>Revisa los campos señalados e inténtalo de nuevo.</div>
                @endif
                @yield('form')
            </div>
        </section>
    </div>
@endsection
