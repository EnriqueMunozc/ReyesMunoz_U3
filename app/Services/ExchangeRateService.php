<?php

namespace App\Services;

use App\Exceptions\ExchangeRateUnavailable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    public function __construct(private FrankfurterClient $client) {}

    public function quote(string $base, string $quote, ?string $amount = null): array
    {
        $key = 'frankfurter:v2:'.$base.':'.$quote;
        $snapshot = Cache::get($key.':valida');
        $usable = $this->usable($snapshot);
        $fresh = $usable && now('UTC')->timestamp - Carbon::parse($snapshot['consultado_en'])->timestamp < max(1, (int) config('services.frankfurter.cache_seconds'));
        $status = 'cache';
        if (! $fresh) {
            $status = 'respaldo';
            if (! Cache::has($key.':reintento')) {
                try {
                    // El bloqueo y la segunda lectura evitan consultas duplicadas al vencer la caché.
                    $snapshot = Cache::lock($key.':bloqueo', max(15, (int) config('services.frankfurter.timeout') + 5))->block(2, function () use ($key, $base, $quote, &$status) {
                        $cached = Cache::get($key.':valida');
                        if ($this->usable($cached) && now('UTC')->timestamp - Carbon::parse($cached['consultado_en'])->timestamp < max(1, (int) config('services.frankfurter.cache_seconds'))) {
                            $status = 'cache';

                            return $cached;
                        }
                        if (Cache::has($key.':reintento')) {
                            throw new ExchangeRateUnavailable;
                        }
                        $data = $this->client->fetch($base, $quote);
                        if (! $this->usable($data)) {
                            throw new ExchangeRateUnavailable;
                        }
                        Cache::forever($key.':valida', $data);
                        Cache::forget($key.':reintento');
                        $status = 'consulta';

                        return $data;
                    });
                } catch (ExchangeRateUnavailable|LockTimeoutException) {
                    Cache::put($key.':reintento', true, max(1, (int) config('services.frankfurter.retry_seconds')));
                    $snapshot = Cache::get($key.':valida');
                    $status = 'respaldo';
                }
            }
            if (! $this->usable($snapshot)) {
                throw new ExchangeRateUnavailable;
            }
        }
        $result = $amount === null ? null : bcadd(bcmul($amount, $snapshot['tasa'], 20), '0.005', 2);

        return $snapshot + ['estado' => $status, 'importe' => $amount === null ? null : bcadd($amount, '0', 2), 'resultado' => $result,
            'redondeo' => 'mitad_hacia_arriba', 'informativa' => true,
            'aviso' => $status === 'respaldo' ? 'El proveedor no está disponible. Se utiliza la última tasa válida dentro del límite de antigüedad.' : 'Tasa de referencia diaria; no es una cotización en tiempo real.'];
    }

    private function usable(?array $data): bool
    {
        if ($data === null) {
            return false;
        }
        $now = now('UTC')->timestamp;
        $limit = max(1, (int) config('services.frankfurter.max_age_seconds'));
        $queried = Carbon::parse($data['consultado_en'])->timestamp;
        $reference = Carbon::parse($data['fecha_referencia'], 'UTC')->startOfDay()->timestamp;

        return $queried <= $now && $now - $queried <= $limit && $reference <= $now && $now - $reference <= $limit;
    }
}
