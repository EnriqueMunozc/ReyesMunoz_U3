<?php

namespace Tests\Feature;

use App\Models\Presupuesto;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\FinanceService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransfersBudgetsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private $source;

    private $target;

    private $expense;

    private $income;

    private FinanceService $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-27 12:00:00', 'America/Mexico_City'));
        $this->owner = User::factory()->create();
        $this->finance = app(FinanceService::class);
        $this->source = $this->finance->save($this->owner, 'cuentas', $this->account());
        $this->target = $this->finance->save($this->owner, 'cuentas', $this->account(['nombre' => 'Destino', 'saldo_inicial' => '0.00']));
        $this->expense = $this->finance->save($this->owner, 'categorias', ['nombre' => 'Alimentos', 'tipo' => 'gasto']);
        $this->income = $this->finance->save($this->owner, 'categorias', ['nombre' => 'Sueldo', 'tipo' => 'ingreso']);
        $this->withToken($this->owner->createToken('Prueba', ['finanzas'], now()->addHours(8))->plainTextToken);
    }

    private function account(array $changes = []): array
    {
        return array_replace(['nombre' => 'Origen', 'tipo' => 'efectivo', 'saldo_inicial' => '100.00', 'fecha_inicial' => '2026-09-01'], $changes);
    }

    private function transfer(array $changes = []): array
    {
        return array_replace(['origen_id' => $this->source->id, 'destino_id' => $this->target->id, 'importe' => '20.00', 'fecha' => '2026-10-10', 'descripcion' => 'Ahorro', 'clave_idempotencia' => (string) Str::uuid()], $changes);
    }

    private function movement(array $changes = []): array
    {
        return array_replace(['cuenta_id' => $this->source->id, 'categoria_id' => $this->expense->id, 'tipo' => 'gasto', 'importe' => '10.00', 'fecha' => '2026-10-11'], $changes);
    }

    private function budget(array $changes = []): array
    {
        return array_replace(['categoria_id' => $this->expense->id, 'mes' => '2026-10', 'limite' => '15.00'], $changes);
    }

    public function test_transferencia_conserva_total_y_no_es_ingreso_gasto_ni_consumo(): void
    {
        $this->postJson('/api/presupuestos', $this->budget())->assertCreated();
        $this->postJson('/api/transferencias', $this->transfer(['importe' => '20.15']))->assertCreated()->assertJsonPath('data.importe', '20.15');
        $this->assertSame('79.85', $this->source->fresh()->saldo);
        $this->assertSame('20.15', $this->target->fresh()->saldo);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.saldo_total', '100.00')
            ->assertJsonPath('data.ingresos_mes', '0.00')->assertJsonPath('data.gastos_mes', '0.00')
            ->assertJsonPath('data.presupuestos_mes.0.indicadores.consumo', '0.00');
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_reenvio_idempotente_normaliza_importes_y_conflicto_no_modifica_original(): void
    {
        $data = $this->transfer(['importe' => '20']);
        $id = $this->postJson('/api/transferencias', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/transferencias', array_replace($data, ['importe' => '20.00']))->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('reutilizada', true);
        $this->postJson('/api/transferencias', array_replace($data, ['importe' => '21.00']))->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');
        $this->finance->remove($this->owner, 'cuentas', $this->source->id);
        $this->postJson('/api/transferencias', $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('transferencias', 1);
        $this->assertSame('80.00', $this->source->fresh()->saldo);
    }

    public function test_transferencias_son_inmutables_y_exigen_cuentas_activas_distintas(): void
    {
        $id = $this->postJson('/api/transferencias', $this->transfer())->json('data.id');
        $this->putJson('/api/transferencias/'.$id, $this->transfer())->assertStatus(405);
        $this->deleteJson('/api/transferencias/'.$id)->assertStatus(405);
        $this->postJson('/api/transferencias', $this->transfer(['destino_id' => $this->source->id]))->assertUnprocessable()->assertJsonValidationErrors('destino_id');
        $this->finance->remove($this->owner, 'cuentas', $this->target->id);
        $this->postJson('/api/transferencias', $this->transfer())->assertUnprocessable();
        $this->assertDatabaseCount('transferencias', 1);
    }

    public function test_sin_fondos_actuales_o_historicos_revierte_ambos_lados(): void
    {
        $this->postJson('/api/transferencias', $this->transfer(['importe' => '100.01']))->assertUnprocessable();
        $this->finance->save($this->owner, 'movimientos', $this->movement(['importe' => '90.00', 'fecha' => '2026-10-15']));
        $this->finance->save($this->owner, 'movimientos', $this->movement(['categoria_id' => $this->income->id, 'tipo' => 'ingreso', 'importe' => '100.00', 'fecha' => '2026-10-20']));
        $this->postJson('/api/transferencias', $this->transfer(['importe' => '20.00']))->assertUnprocessable();
        $this->assertDatabaseCount('transferencias', 0);
        $this->assertSame('110.00', $this->source->fresh()->saldo);
        $this->assertSame('0.00', $this->target->fresh()->saldo);
        $this->assertDatabaseCount('operaciones_financieras', 2);
    }

    public function test_editar_eliminar_o_mover_ingreso_no_desfinancia_transferencia_posterior(): void
    {
        $this->finance->save($this->owner, 'cuentas', $this->account(['saldo_inicial' => '0.00']), $this->source->id);
        $income = $this->finance->save($this->owner, 'movimientos', $this->movement(['categoria_id' => $this->income->id, 'tipo' => 'ingreso', 'importe' => '50.00', 'fecha' => '2026-10-01']));
        $this->postJson('/api/transferencias', $this->transfer(['importe' => '40.00']))->assertCreated();
        foreach ([['importe' => '30.00'], ['fecha' => '2026-10-20'], ['cuenta_id' => $this->target->id]] as $change) {
            $this->putJson('/api/movimientos/'.$income->id, $this->movement(array_replace(['categoria_id' => $this->income->id, 'tipo' => 'ingreso', 'importe' => '50.00', 'fecha' => '2026-10-01'], $change)))->assertUnprocessable();
        }
        $this->deleteJson('/api/movimientos/'.$income->id)->assertUnprocessable();
        $this->assertSame('50.00', $income->fresh()->importe);
        $this->assertSame('10.00', $this->source->fresh()->saldo);
        $this->assertSame('40.00', $this->target->fresh()->saldo);
    }

    public function test_orden_global_mismo_dia_permite_gastar_abono_y_no_anticipa_ingresos(): void
    {
        $this->postJson('/api/transferencias', $this->transfer())->assertCreated();
        $this->postJson('/api/movimientos', $this->movement(['cuenta_id' => $this->target->id, 'importe' => '20.00', 'fecha' => '2026-10-10']))->assertCreated();
        $income = $this->finance->save($this->owner, 'movimientos', $this->movement(['categoria_id' => $this->income->id, 'tipo' => 'ingreso', 'importe' => '100.00', 'fecha' => '2026-10-20']));
        $this->postJson('/api/transferencias', $this->transfer(['importe' => '90.00', 'fecha' => '2026-10-10']))->assertUnprocessable();
        $this->assertSame('180.00', $this->source->fresh()->saldo);
    }

    public function test_cuentas_con_transferencias_se_archivan_y_bloquean_datos_iniciales(): void
    {
        $this->postJson('/api/transferencias', $this->transfer())->assertCreated();
        foreach ([$this->source, $this->target] as $account) {
            $this->putJson('/api/cuentas/'.$account->id, $this->account(['saldo_inicial' => '999.00']))->assertUnprocessable();
            $this->putJson('/api/cuentas/'.$account->id, $this->account(['saldo_inicial' => $account->saldo_inicial, 'fecha_inicial' => '2026-08-01']))->assertUnprocessable();
            $this->deleteJson('/api/cuentas/'.$account->id)->assertOk();
            $this->assertTrue($account->fresh()->archivada);
            $this->actingAs($this->owner)->get('/cuentas/'.$account->id.'/edit')->assertOk()->assertSee('movimientos o transferencias');
            $this->app['auth']->forgetGuards();
        }
        $this->assertDatabaseCount('cuentas', 2);
        $this->getJson('/api/transferencias')->assertJsonPath('meta.total', 1);
    }

    public function test_fechas_importes_y_claves_invalidos_no_crean_operaciones(): void
    {
        foreach (['0', '-1', '1.001', '1e2', '1,00', 1.25] as $amount) {
            $this->postJson('/api/transferencias', $this->transfer(['importe' => $amount]))->assertUnprocessable();
        }
        foreach (['2026-08-31', '2026-10-28', '2026-02-30'] as $date) {
            $this->postJson('/api/transferencias', $this->transfer(['fecha' => $date]))->assertUnprocessable();
        }
        $this->finance->save($this->owner, 'cuentas', $this->account(['saldo_inicial' => '0.00', 'fecha_inicial' => '2026-10-15']), $this->target->id);
        $this->postJson('/api/transferencias', $this->transfer())->assertUnprocessable();
        $this->postJson('/api/transferencias', $this->transfer(['clave_idempotencia' => 'invalida']))->assertUnprocessable();
        $this->assertDatabaseCount('operaciones_financieras', 0);
    }

    public function test_aislamiento_web_api_y_referencias_ajenas(): void
    {
        $transfer = app(TransferService::class)->create($this->owner, $this->transfer())['transferencia'];
        $budget = app(BudgetService::class)->save($this->owner, $this->budget());
        $other = User::factory()->create();
        $this->withToken($other->createToken('Otra persona', ['finanzas'])->plainTextToken)->actingAs($other);
        foreach (['transferencias' => $transfer, 'presupuestos' => $budget] as $resource => $model) {
            $this->getJson('/api/'.$resource.'/'.$model->id)->assertNotFound();
            $this->get('/'.$resource.'/'.$model->id)->assertNotFound();
            $this->getJson('/api/'.$resource)->assertJsonPath('meta.total', 0);
        }
        $this->putJson('/api/presupuestos/'.$budget->id, $this->budget())->assertNotFound();
        $this->deleteJson('/api/presupuestos/'.$budget->id)->assertNotFound();
        $this->get('/presupuestos/'.$budget->id.'/edit')->assertNotFound();
        $this->postJson('/api/transferencias', $this->transfer())->assertUnprocessable()->assertJsonValidationErrors(['origen_id', 'destino_id']);
        $this->postJson('/api/presupuestos', $this->budget())->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
        $this->get('/transferencias/create')->assertDontSee('Origen ·')->assertDontSee('Destino ·');
        $this->get('/presupuestos/create')->assertDontSee('Alimentos');
        $this->getJson('/api/transferencias?cuenta_id='.$this->source->id)->assertUnprocessable();
    }

    public function test_presupuesto_se_actualiza_con_gastos_archivados_y_superar_limite_no_bloquea(): void
    {
        $budget = app(BudgetService::class)->save($this->owner, $this->budget());
        $movement = $this->finance->save($this->owner, 'movimientos', $this->movement(['importe' => '20.00']));
        $this->getJson('/api/presupuestos/'.$budget->id)->assertJsonPath('data.indicadores.consumo', '20.00')
            ->assertJsonPath('data.indicadores.disponible', '0.00')->assertJsonPath('data.indicadores.exceso', '5.00')
            ->assertJsonPath('data.indicadores.porcentaje_utilizado', '133.33')->assertJsonPath('data.indicadores.ancho_barra', '100.00');
        $this->finance->remove($this->owner, 'cuentas', $this->source->id);
        $this->assertSame('20.00', $budget->fresh()->indicadores['consumo']);
        $this->finance->save($this->owner, 'cuentas', $this->account(['archivada' => false]), $this->source->id);
        $this->putJson('/api/movimientos/'.$movement->id, $this->movement(['importe' => '10.00']))->assertOk();
        $this->assertSame('5.00', $budget->fresh()->indicadores['disponible']);
        $this->deleteJson('/api/movimientos/'.$movement->id)->assertOk();
        $this->assertSame('0.00', $budget->fresh()->indicadores['consumo']);
        $this->assertSame('15.00', $budget->fresh()->indicadores['disponible']);
    }

    public function test_periodos_categorias_y_duplicados_de_presupuesto(): void
    {
        $id = $this->postJson('/api/presupuestos', $this->budget())->assertCreated()->json('data.id');
        $this->postJson('/api/presupuestos', $this->budget())->assertUnprocessable()->assertJsonValidationErrors('mes');
        foreach (['2026-00', '2026-13', '2026-1', '2026-10-01', '0000-01', '2200-01'] as $month) {
            $this->postJson('/api/presupuestos', $this->budget(['mes' => $month]))->assertUnprocessable();
        }
        foreach (['0', '-1', '1.001', '1e3', 10.25] as $limit) {
            $this->postJson('/api/presupuestos', $this->budget(['limite' => $limit]))->assertUnprocessable();
        }
        $this->postJson('/api/presupuestos', $this->budget(['categoria_id' => $this->income->id]))->assertUnprocessable();
        $this->putJson('/api/presupuestos/'.$id, $this->budget(['limite' => '50.10']))->assertOk()->assertJsonPath('data.limite', '50.10');
        $this->deleteJson('/api/categorias/'.$this->expense->id)->assertUnprocessable();
        $this->putJson('/api/categorias/'.$this->expense->id, ['nombre' => 'Alimentos', 'tipo' => 'ingreso'])->assertUnprocessable();
        $this->deleteJson('/api/presupuestos/'.$id)->assertOk();
        $this->getJson('/api/presupuestos/'.$id)->assertNotFound();
    }

    public function test_consumo_excluye_otro_mes_ingresos_y_otra_categoria(): void
    {
        $budget = app(BudgetService::class)->save($this->owner, $this->budget());
        $this->finance->save($this->owner, 'movimientos', $this->movement(['fecha' => '2026-09-15']));
        $this->finance->save($this->owner, 'movimientos', $this->movement(['categoria_id' => $this->income->id, 'tipo' => 'ingreso']));
        $category = $this->finance->save($this->owner, 'categorias', ['nombre' => 'Otro', 'tipo' => 'gasto']);
        $this->finance->save($this->owner, 'movimientos', $this->movement(['categoria_id' => $category->id]));
        $this->assertSame('0.00', $budget->fresh()->indicadores['consumo']);
        $this->getJson('/api/dashboard')->assertJsonPath('data.total_presupuestos_mes', 1);
    }

    public function test_formulario_web_reenvia_misma_clave_y_muestra_inmutabilidad(): void
    {
        $this->actingAs($this->owner);
        $html = $this->get('/transferencias/create')->assertOk()->assertSee('no se pueden editar ni eliminar')->getContent();
        preg_match('/name="clave_idempotencia" value="([^"]+)"/', $html, $match);
        $payload = $this->transfer(['clave_idempotencia' => $match[1]]);
        $this->post('/transferencias', $payload)->assertRedirect();
        $this->post('/transferencias', $payload)->assertRedirect()->assertSessionHas('status', 'La transferencia ya estaba registrada. No se duplicó la operación.');
        $this->assertDatabaseCount('transferencias', 1);
        $this->post('/presupuestos', $this->budget())->assertRedirect();
        $budget = Presupuesto::sole();
        $this->get('/presupuestos/'.$budget->id)->assertOk()->assertSee('Porcentaje utilizado');
        $this->get('/dashboard')->assertOk()->assertSee('Presupuestos de 2026-10');
    }

    public function test_consumo_cambia_al_reclasificar_o_cambiar_periodo_y_no_se_mezcla_entre_usuarios(): void
    {
        $budget = app(BudgetService::class)->save($this->owner, $this->budget());
        $movement = $this->finance->save($this->owner, 'movimientos', $this->movement());
        $this->assertSame('10.00', $budget->indicadores['consumo']);
        $this->putJson('/api/movimientos/'.$movement->id, $this->movement(['fecha' => '2026-09-15']))->assertOk();
        $this->assertSame('0.00', $budget->indicadores['consumo']);
        $otherCategory = $this->finance->save($this->owner, 'categorias', ['nombre' => 'Transporte', 'tipo' => 'gasto']);
        $this->putJson('/api/movimientos/'.$movement->id, $this->movement(['categoria_id' => $otherCategory->id]))->assertOk();
        $this->assertSame('0.00', $budget->indicadores['consumo']);
        $other = User::factory()->create();
        $account = $this->finance->save($other, 'cuentas', $this->account());
        $category = $this->finance->save($other, 'categorias', ['nombre' => 'Alimentos', 'tipo' => 'gasto']);
        $otherBudget = app(BudgetService::class)->save($other, $this->budget(['categoria_id' => $category->id]));
        $this->finance->save($other, 'movimientos', $this->movement(['cuenta_id' => $account->id, 'categoria_id' => $category->id]));
        $this->assertSame('10.00', $otherBudget->indicadores['consumo']);
        $this->assertSame('0.00', $budget->indicadores['consumo']);
    }

    public function test_filtros_paginacion_y_rutas_protegidas(): void
    {
        $this->postJson('/api/transferencias', $this->transfer())->assertCreated();
        $this->postJson('/api/transferencias', $this->transfer())->assertCreated();
        $this->getJson('/api/transferencias?cuenta_id='.$this->target->id.'&desde=2026-10-01&hasta=2026-10-27&per_page=1&page=2')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->get('/transferencias?cuenta_id='.$this->source->id.'&per_page=1')->assertSee('page=2', false)->assertSee('cuenta_id=', false);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/presupuestos', $this->budget())->assertCreated();
        $this->postJson('/api/presupuestos', $this->budget(['mes' => '2026-11']))->assertCreated();
        $this->getJson('/api/presupuestos?mes=2026-10&categoria_id='.$this->expense->id)->assertJsonPath('meta.total', 1);
        $this->get('/presupuestos?per_page=1')->assertSee('page=2', false);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->get('/transferencias')->assertRedirect(route('login'));
        $this->get('/presupuestos')->assertRedirect(route('login'));
        $this->get('/api/transferencias')->assertUnauthorized();
        $this->get('/api/presupuestos')->assertUnauthorized();
    }
}
