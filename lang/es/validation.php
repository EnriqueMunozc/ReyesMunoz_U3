<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'Introduce un correo electrónico válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'unique' => 'Este :attribute ya está registrado.',
    'integer' => 'El campo :attribute debe ser un identificador entero.',
    'boolean' => 'El campo :attribute debe indicar sí o no.',
    'in' => 'El valor de :attribute no es válido.',
    'exists' => 'El recurso seleccionado no está disponible para tu usuario.',
    'prohibited' => 'No debes enviar el campo :attribute.',
    'regex' => 'Usa una cadena decimal sin comas, con máximo 13 enteros y dos decimales.',
    'date_format' => 'Usa una fecha válida con formato AAAA-MM-DD.',
    'before_or_equal' => 'La fecha no debe ser posterior a :date.',
    'after_or_equal' => 'La fecha final no debe ser anterior a la inicial.',
    'between' => ['numeric' => 'El campo :attribute debe estar entre :min y :max.'],
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'numeric' => 'El campo :attribute debe ser al menos :min.'],
    'password' => [
        'letters' => 'La contraseña debe incluir al menos una letra.',
        'numbers' => 'La contraseña debe incluir al menos un número.',
    ],
    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'nombre' => 'nombre', 'tipo' => 'tipo', 'importe' => 'importe', 'saldo_inicial' => 'saldo inicial',
        'fecha' => 'fecha', 'fecha_inicial' => 'fecha inicial', 'cuenta_id' => 'cuenta', 'categoria_id' => 'categoría',
        'descripcion' => 'descripción', 'archivada' => 'estado de archivo', 'user_id' => 'propietario',
        'nombre_dispositivo' => 'nombre del dispositivo', 'per_page' => 'registros por página', 'page' => 'página',
    ],
];
