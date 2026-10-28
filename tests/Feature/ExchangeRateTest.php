<?php

namespace Tests\Feature;

use App\Exceptions\ExchangeRateUnavailable;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('UTC')->setDate(2026, 10, 23)->setTime(18, 0));
        Http::preventStrayRequests();
        config(['services.frankfurter.cache_seconds' => 3600, 'services.frankfurter.max_age_seconds' => 604800,
            'services.frankfurter.retry_seconds' => 60]);
        Cache::flush();
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('Conversor', ['finanzas'], now()->addHours(8))->plainTextToken);

        return $user;
    }

    private function fake(array|callable $responses = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function response(string $rate = '18.123456789012345678', string $base = 'USD', string $quote = 'MXN', string $date = '2026-10-23'): string
    {
        return '{"date":"'.$date.'","base":"'.$base.'","quote":"'.$quote.'","rate":'.$rate.'}';
    }

    public function test_ambas_direcciones_conservan_precision_y_redondean_sin_float(): void
    {
        $this->authenticate();
        $this->fake(['*/USD/MXN' => Http::response($this->response()),
            '*/MXN/USD' => Http::response($this->response('0.055', 'MXN', 'USD'))]);
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN&importe=1.00')->assertOk()
            ->assertJsonPath('data.tasa', '18.123456789012345678')->assertJsonPath('data.resultado', '18.12')
            ->assertJsonPath('data.importe', '1.00')->assertJsonPath('data.estado', 'consulta')
            ->assertJsonPath('data.proveedor', 'Frankfurter')->assertJsonPath('data.fecha_referencia', '2026-10-23');
        $this->getJson('/api/tipo-cambio?base=MXN&destino=USD&importe=1.00')->assertOk()
            ->assertJsonPath('data.resultado', '0.06')->assertJsonPath('data.base', 'MXN');
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertOk()->assertJsonPath('data.resultado', null);
        Http::assertSentCount(2);
    }

    public function test_redondeo_en_limites_y_montos_maximos(): void
    {
        $this->fake(['*' => Http::response($this->response('1.005'))]);
        $service = app(ExchangeRateService::class);
        $this->assertSame('1.01', $service->quote('USD', 'MXN', '1')['resultado']);
        $this->assertSame('1004999999.99', $service->quote('USD', 'MXN', '999999999.99')['resultado']);
        Cache::flush();
        $this->fake(['*' => Http::response($this->response('1.004999999999999999'))]);
        $this->assertSame('1.00', $service->quote('USD', 'MXN', '1')['resultado']);
    }

    public function test_parametros_invalidos_no_consultan_al_proveedor(): void
    {
        $this->authenticate();
        $this->fake();
        foreach (['0', '-1', '1.001', '1e2', '1,00', '1000000000', 'importe[]=1'] as $value) {
            $query = str_starts_with($value, 'importe[') ? $value : 'importe='.urlencode($value);
            $this->getJson('/api/tipo-cambio?base=USD&destino=MXN&'.$query)->assertUnprocessable()->assertJsonValidationErrors('importe');
        }
        foreach (['', 'base=EUR&destino=MXN', 'base=USD&destino=USD', 'base[]=USD&destino=MXN', 'base=USD'] as $query) {
            $this->getJson('/api/tipo-cambio?'.$query)->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_autenticacion_web_api_y_habilidad(): void
    {
        $this->fake();
        $this->get('/conversor')->assertRedirect(route('login'));
        $this->get('/api/tipo-cambio?base=USD&destino=MXN')->assertUnauthorized();
        $user = User::factory()->create();
        $this->withToken($user->createToken('Sin permiso', ['otro'])->plainTextToken)
            ->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_web_comparte_cache_con_api_y_muestra_metadatos(): void
    {
        $user = $this->authenticate();
        $this->fake(['*' => Http::response($this->response('18.5'))]);
        $this->actingAs($user)->get('/conversor?base=USD&destino=MXN&importe=2')->assertOk()
            ->assertSee('2.00 USD = 37.00 MXN')->assertSee('Tasa recién consultada')->assertSee('2026-10-23')
            ->assertSee('Frankfurter')->assertSee('No registra movimientos', false);
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN&importe=3')->assertOk()
            ->assertJsonPath('data.estado', 'cache')->assertJsonPath('data.resultado', '55.50');
        Http::assertSentCount(1);
    }

    public function test_cache_vence_y_se_renueva_con_otra_tasa(): void
    {
        $this->authenticate();
        $this->fake(['*' => Http::sequence()->push($this->response('18'))->push($this->response('19'))]);
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertJsonPath('data.estado', 'consulta');
        $this->travel(3599)->seconds();
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertJsonPath('data.estado', 'cache');
        $this->travel(1)->seconds();
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertJsonPath('data.estado', 'consulta')->assertJsonPath('data.tasa', '19.000000000000000000');
        Http::assertSentCount(2);
    }

    public function test_fallas_externas_sin_datos_devuelven_503_y_no_se_reintenta_cada_conversion(): void
    {
        $this->authenticate();
        $invalid = [
            Http::response('', 500), Http::response('no es JSON'), Http::response('[]'),
            Http::response($this->response('-1')), Http::response($this->response('0')),
            Http::response($this->response('18', 'EUR')), Http::response($this->response('18', 'USD', 'EUR')),
            Http::response($this->response('18', 'USD', 'MXN', '2026-02-30')),
            Http::response($this->response('18', 'USD', 'MXN', '2026-10-24')),
            Http::response('{"base":"USD","quote":"MXN","rate":true,"date":"2026-10-23"}'),
            Http::response('{"base":"USD","quote":"MXN","date":"2026-10-23"}'),
        ];
        foreach ($invalid as $response) {
            Cache::flush();
            $this->fake(['*' => $response]);
            $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertStatus(503)
                ->assertJsonPath('code', 'tipo_cambio_no_disponible')->assertHeader('Retry-After', '60');
            $this->getJson('/api/tipo-cambio?base=USD&destino=MXN&importe=1')->assertStatus(503);
            Http::assertSentCount(1);
        }
        Cache::flush();
        $this->fake(fn () => throw new ConnectionException('timeout interno'));
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertStatus(503)->assertDontSee('timeout interno');
    }

    public function test_respaldo_permitido_y_demasiado_antiguo(): void
    {
        $this->authenticate();
        $this->fake(['*' => Http::sequence()->push($this->response('18'))->push('', 503)->push('', 503)]);
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertOk();
        $this->travel(2)->hours();
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN&importe=2')->assertOk()
            ->assertJsonPath('data.estado', 'respaldo')->assertJsonPath('data.resultado', '36.00')
            ->assertJsonPath('data.consultado_en', '2026-10-23T18:00:00+00:00');
        $this->travel(8)->days();
        Auth::forgetGuards();
        $this->authenticate();
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertStatus(503);
        Http::assertSentCount(3);
    }

    public function test_dato_recien_consultado_con_referencia_antigua_tambien_se_rechaza(): void
    {
        $this->authenticate();
        $this->fake(['*' => Http::response($this->response('18', 'USD', 'MXN', '2026-10-01'))]);
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN')->assertStatus(503);
    }

    public function test_convertir_o_fallar_no_modifica_movimientos_ni_saldos(): void
    {
        $user = $this->authenticate();
        $finance = app(FinanceService::class);
        $account = $finance->save($user, 'cuentas', ['nombre' => 'Ahorro', 'tipo' => 'ahorro', 'saldo_inicial' => '1250.50', 'fecha_inicial' => '2026-10-01']);
        $category = $finance->save($user, 'categorias', ['nombre' => 'Alimentos', 'tipo' => 'gasto']);
        $movement = $finance->save($user, 'movimientos', ['cuenta_id' => $account->id, 'categoria_id' => $category->id,
            'tipo' => 'gasto', 'importe' => '125.50', 'fecha' => '2026-10-20']);
        $originalMovement = $movement->getAttributes();
        $before = $finance->dashboard($user);
        $this->fake(['*' => Http::response($this->response('18'))]);
        $this->getJson('/api/tipo-cambio?base=USD&destino=MXN&importe=100')->assertOk();
        Cache::flush();
        $this->fake(fn () => throw new ConnectionException);
        $this->actingAs($user)->get('/conversor?base=MXN&destino=USD&importe=100')->assertStatus(503)->assertSee('No hay una tasa de cambio utilizable');
        $this->get('/dashboard')->assertOk()->assertSee('$1,125.00 MXN');
        $this->get('/cuentas')->assertOk();
        $this->get('/movimientos')->assertOk();
        $this->assertSame($before['saldo_total'], $finance->dashboard($user)['saldo_total']);
        $this->assertDatabaseCount('movimientos', 1);
        $this->assertDatabaseCount('cuentas', 1);
        $this->assertSame($originalMovement, $movement->fresh()->getAttributes());
    }

    public function test_configuracion_tls_y_timeouts_se_aplican(): void
    {
        config(['services.frankfurter.ca_bundle' => false, 'services.frankfurter.timeout' => 6, 'services.frankfurter.connect_timeout' => 2]);
        $this->fake(function ($request, $options) {
            $this->assertTrue($options['verify']);
            $this->assertSame(6, $options['timeout']);
            $this->assertSame(2, $options['connect_timeout']);

            return Http::response($this->response('18'));
        });
        app(ExchangeRateService::class)->quote('USD', 'MXN');
    }

    public function test_timeout_utiliza_respaldo_y_recupera_el_proveedor_tras_la_pausa(): void
    {
        $service = app(ExchangeRateService::class);
        $this->fake(['*' => Http::response($this->response('18'))]);
        $first = $service->quote('USD', 'MXN');
        $this->travel(3600)->seconds();
        $this->fake(fn () => throw new ConnectionException('timeout simulado'));
        $this->assertSame('respaldo', $service->quote('USD', 'MXN', '2')['estado']);
        $this->fake(['*' => Http::response($this->response('19'))]);
        $this->assertSame($first['consultado_en'], $service->quote('USD', 'MXN')['consultado_en']);
        Http::assertNothingSent();
        $this->travel(60)->seconds();
        $this->assertSame('consulta', $service->quote('USD', 'MXN')['estado']);
        $this->assertSame('19.000000000000000000', $service->quote('USD', 'MXN')['tasa']);
        Http::assertSentCount(1);
    }

    public function test_web_muestra_advertencia_de_respaldo_y_valida_importes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->fake(['*' => Http::response($this->response('18'))]);
        $this->get('/conversor')->assertOk()->assertSee('Tasa recién consultada');
        $this->from('/conversor')->get('/conversor?base=USD&destino=MXN&importe=-1')->assertSessionHasErrors('importe');
        $this->travel(3600)->seconds();
        $this->fake(['*' => Http::response('JSON inválido')]);
        $this->get('/conversor?base=USD&destino=MXN&importe=1')->assertOk()
            ->assertSee('Tasa de respaldo por falla del proveedor')->assertSee('última tasa válida');
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_antiguedad_maxima_y_duracion_de_cache_son_configurables(): void
    {
        config(['services.frankfurter.cache_seconds' => 10, 'services.frankfurter.max_age_seconds' => 86400]);
        $service = app(ExchangeRateService::class);
        $this->fake(['*' => Http::response($this->response('18'))]);
        $service->quote('USD', 'MXN');
        $this->travel(10)->seconds();
        $this->fake(['*' => Http::response('', 502)]);
        $this->assertSame('respaldo', $service->quote('USD', 'MXN')['estado']);
        $this->travel(6)->hours();
        $this->expectException(ExchangeRateUnavailable::class);
        $service->quote('USD', 'MXN');
    }
}
