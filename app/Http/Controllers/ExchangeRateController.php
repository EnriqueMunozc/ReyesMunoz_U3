<?php

namespace App\Http\Controllers;

use App\Exceptions\ExchangeRateUnavailable;
use App\Http\Requests\ExchangeRateRequest;
use App\Services\ExchangeRateService;

class ExchangeRateController extends Controller
{
    public function __invoke(ExchangeRateRequest $request, ExchangeRateService $service)
    {
        $input = $request->validated();
        try {
            $data = $service->quote($input['base'], $input['destino'], $input['importe'] ?? null);

            return $request->is('api/*') ? response()->json(['data' => $data]) : view('exchange', ['data' => $data, 'input' => $input, 'error' => null]);
        } catch (ExchangeRateUnavailable $exception) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $exception->getMessage(), 'code' => 'tipo_cambio_no_disponible'], 503)
                    ->header('Retry-After', (string) max(1, (int) config('services.frankfurter.retry_seconds')));
            }

            return response()->view('exchange', ['data' => null, 'input' => $input, 'error' => $exception->getMessage()], 503);
        }
    }
}
