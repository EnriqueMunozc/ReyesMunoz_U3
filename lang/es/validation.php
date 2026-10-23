<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'Introduce un correo electrónico válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'unique' => 'Este :attribute ya está registrado.',
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'password' => [
        'letters' => 'La contraseña debe incluir al menos una letra.',
        'numbers' => 'La contraseña debe incluir al menos un número.',
    ],
    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
    ],
];
