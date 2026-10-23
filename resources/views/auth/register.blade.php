@extends('layouts.auth')
@section('title', 'Crear cuenta')
@section('heading', 'Tu primer paso empieza aquí')
@section('description', 'Crea tu cuenta para entrar a Cuentahorro.')

@section('form')
    <form method="POST" action="{{ route('register') }}" data-submit-form>
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Nombre</label>
            <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" autocomplete="name" maxlength="100" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<div id="name-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="username" maxlength="254" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<div id="email-error" class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <div class="input-group has-validation">
                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                <button type="button" class="btn btn-password" data-toggle-password="password" aria-controls="password" aria-pressed="false">Mostrar</button>
                @error('password')<div id="password-error" class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text" id="password-help">Usa al menos 8 caracteres, con letras y números.</div>
        </div>
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirma tu contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" minlength="8" maxlength="72" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-3" data-loading-text="Creando tu cuenta…">Crear mi cuenta <span aria-hidden="true">→</span></button>
        <p class="text-secondary text-center small mt-4 mb-0">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a></p>
    </form>
@endsection
