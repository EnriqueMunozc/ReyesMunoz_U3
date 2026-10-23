<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173e35">
    <title>@yield('title', 'Inicio') · Cuentahorro</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
    <a href="#contenido" class="visually-hidden-focusable skip-link">Saltar al contenido</a>
    <header class="site-header">
        <nav class="container d-flex flex-wrap align-items-center justify-content-between gap-3 py-3" aria-label="Navegación principal">
            <a class="brand" href="{{ route('home') }}" aria-label="Cuentahorro, inicio"><span class="brand-mark" aria-hidden="true">c<span>•</span></span>Cuentahorro</a>
            <div class="d-flex flex-wrap align-items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="nav-link active" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">Inicio</a>
                    <form method="POST" action="{{ route('logout') }}" data-submit-form>
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit" data-loading-text="Cerrando sesión…">Cerrar sesión</button>
                    </form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Iniciar sesión</a>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('register') }}">Crear cuenta</a>
                @endauth
            </div>
        </nav>
    </header>
    <main id="contenido" class="container py-4 py-lg-5">
        @if (session('status'))
            <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
    <footer class="container site-footer d-flex flex-wrap justify-content-between gap-2 py-4">
        <span>Cuentahorro · A tu ritmo, con claridad.</span>
        <span>Finanzas personales en pesos mexicanos</span>
    </footer>
</body>
</html>
