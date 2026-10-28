<?php

namespace App\Services;

use App\Exceptions\ExchangeRateUnavailable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

class FrankfurterClient
{
    public function fetch(string $base, string $quote): array
    {
        try {
            $url = rtrim(config('services.frankfurter.url'), '/');
            if (! str_starts_with($url, 'https://')) {
                throw new ExchangeRateUnavailable;
            }
            $ca = config('services.frankfurter.ca_bundle');
            $response = Http::withOptions(['verify' => is_string($ca) && $ca !== '' ? $ca : true, 'allow_redirects' => false])
                ->connectTimeout(max(1, (int) config('services.frankfurter.connect_timeout')))
                ->timeout(max(1, (int) config('services.frankfurter.timeout')))->acceptJson()
                ->get($url.'/v2/rate/'.$base.'/'.$quote)->throw();
            if ($response->status() !== 200 || strlen($response->body()) > 16384) {
                throw new ExchangeRateUnavailable;
            }
            // Conserva los literales numéricos del JSON antes de decodificar: nunca pasan por float.
            $json = preg_replace_callback('~"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?~',
                fn ($match) => '"'.$match[0].'"', $response->body());
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            $date = is_array($data) ? ($data['date'] ?? null) : null;
            $reference = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC')) : false;
            $rate = is_array($data) ? ($data['rate'] ?? null) : null;
            if (($data['base'] ?? null) !== $base || ($data['quote'] ?? null) !== $quote
                || ! $reference || $reference->format('Y-m-d') !== $date || $date > now('UTC')->toDateString()
                || ! is_string($rate) || ! preg_match('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,18})?$/D', $rate)
                || bccomp($rate, '0', 18) <= 0) {
                throw new ExchangeRateUnavailable;
            }

            return ['base' => $base, 'destino' => $quote, 'tasa' => bcadd($rate, '0', 18),
                'fecha_referencia' => $date, 'consultado_en' => now('UTC')->toIso8601String(), 'proveedor' => 'Frankfurter'];
        } catch (Throwable) {
            throw new ExchangeRateUnavailable;
        }
    }
}
