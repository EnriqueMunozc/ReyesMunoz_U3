<?php

namespace App\Exceptions;

use RuntimeException;

class ExchangeRateUnavailable extends RuntimeException
{
    public const MESSAGE = 'No hay una tasa de cambio utilizable en este momento. Intenta de nuevo más tarde.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
