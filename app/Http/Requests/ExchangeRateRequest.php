<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base' => ['required', 'string', Rule::in(['USD', 'MXN'])],
            'destino' => ['required', 'string', Rule::in(['USD', 'MXN']), 'different:base'],
            'importe' => ['bail', 'nullable', 'string', 'max:12', 'regex:/^(?:0|[1-9]\d{0,8})(?:\.\d{1,2})?$/D', function ($attribute, $value, $fail) {
                if (is_string($value) && preg_match('/^\d+(?:\.\d{1,2})?$/D', $value) && bccomp($value, '0', 2) <= 0) {
                    $fail('El importe debe ser mayor que cero.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['importe.regex' => 'Introduce un importe positivo de hasta 999999999.99, sin comas y con máximo dos decimales.',
            'destino.different' => 'Selecciona monedas distintas para la conversión.'];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->is('api/*') && ! $this->hasAny(['base', 'destino', 'importe'])) {
            $this->merge(['base' => 'USD', 'destino' => 'MXN']);
        }
    }
}
