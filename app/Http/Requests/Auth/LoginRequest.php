<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:72',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('La contraseña es demasiado larga. Usa menos caracteres.');
                    }
                }],
        ];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', $this->string('email')->toString().'|'.$this->ip());
        $ipKey = 'login-ip:'.hash('sha256', (string) $this->ip());

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            event(new Lockout($this));
            $seconds = max(RateLimiter::availableIn($key), RateLimiter::availableIn($ipKey));
            throw new HttpResponseException(response()->view('errors.429', ['seconds' => $seconds], 429)
                ->header('Retry-After', (string) $seconds)->header('Cache-Control', 'no-store, private'));
        }

        if (! Auth::attempt($this->only('email', 'password'))) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
    }
}
