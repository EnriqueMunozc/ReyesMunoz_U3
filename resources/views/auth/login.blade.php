@extends('layouts.auth')
@section('title', 'Iniciar sesión')
@section('heading', 'Qué gusto verte de nuevo')
@section('description', 'Entra a tu espacio con tu correo y contraseña.')

@section('form')
    <form method="POST" action="{{ route('login') }}" data-submit-form>
        @csrf
        <div class="mb-4">
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" maxlength="254" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<div id="email-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Contraseña</label>
            <div class="input-group has-validation">
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" maxlength="72" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="btn btn-password" data-toggle-password="password" aria-controls="password" aria-pressed="false">Mostrar</button>
                @error('password')<div id="password-error" class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-3" data-loading-text="Iniciando sesión…">Iniciar sesión <span aria-hidden="true">→</span></button>
        <p class="text-secondary text-center small mt-4 mb-0">¿Es tu primera vez? <a href="{{ route('register') }}">Crea tu cuenta</a></p>
    </form>
@endsection
