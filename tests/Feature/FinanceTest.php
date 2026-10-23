<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $owner;

    private Cuenta $account;

    private Categoria $income;

    private Categoria $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00', 'America/Mexico_City'));
        $this->finance = app(FinanceService::class);
        $this->owner = User::factory()->create();
        $this->account = $this->finance->save($this->owner, 'cuentas', $this->accountData());
        $this->income = $this->finance->save($this->owner, 'categorias', ['nombre' => 'Sueldo', 'tipo' => 'ingreso']);
        $this->expense = $this->finance->save($this->owner, 'categorias', ['nombre' => 'Comida', 'tipo' => 'gasto']);
    }

    private function accountData(array $changes = []): array
    {
        return array_merge(['nombre' => 'Efectivo', 'tipo' => 'efectivo', 'saldo_inicial' => '100.00', 'fecha_inicial' => '2026-09-01'], $changes);
    }

    private function movementData(array $changes = []): array
    {
        return array_merge(['cuenta_id' => $this->account->id, 'categoria_id' => $this->expense->id, 'tipo' => 'gasto', 'importe' => '10.00', 'fecha' => '2026-10-10', 'descripcion' => 'Prueba'], $changes);
    }

    private function token(?User $user = null, ?\DateTimeInterface $expires = null): string
    {
        return ($user ?? $this->owner)->createToken('Pruebas', ['finanzas'], $expires ?? now()->addHour())->plainTextToken;
    }

    public function test_real_balances_use_decimals_and_initial_balance_is_not_monthly_income(): void
    {
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['tipo' => 'ingreso', 'categoria_id' => $this->income->id, 'importe' => '0.10']));
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['tipo' => 'ingreso', 'categoria_id' => $this->income->id, 'importe' => '0.20']));
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['importe' => '20.10']));
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['fecha' => '2026-09-15', 'importe' => '5.00']));
        $data = $this->finance->dashboard($this->owner);
        $this->assertSame('75.20', $data['saldo_total']);
        $this->assertSame('0.30', $data['ingresos_mes']);
        $this->assertSame('20.10', $data['gastos_mes']);
        $this->assertSame('-19.80', $data['balance_mes']);
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('$75.20 MXN')->assertSee('Saldo total activo');
    }

    public function test_archived_accounts_are_excluded_from_total_but_keep_monthly_history(): void
    {
        $this->finance->save($this->owner, 'movimientos', $this->movementData());
        $this->assertStringContainsString('archivada', $this->finance->remove($this->owner, 'cuentas', $this->account->id));
        $data = $this->finance->dashboard($this->owner);
        $this->assertSame('0.00', $data['saldo_total']);
        $this->assertSame('10.00', $data['gastos_mes']);
        $this->assertDatabaseCount('movimientos', 1);
        $this->actingAs($this->owner)->post('/movimientos', $this->movementData())->assertSessionHasErrors('cuenta_id');
        $this->finance->save($this->owner, 'cuentas', $this->accountData(['archivada' => false]), $this->account->id);
        $this->assertSame('90.00', $this->finance->dashboard($this->owner)['saldo_total']);
    }

    public function test_only_empty_accounts_can_be_deleted_and_initial_data_becomes_immutable(): void
    {
        $empty = $this->finance->save($this->owner, 'cuentas', $this->accountData(['nombre' => 'Vacía']));
        $this->finance->remove($this->owner, 'cuentas', $empty->id);
        $this->assertDatabaseMissing('cuentas', ['id' => $empty->id]);
        $this->finance->save($this->owner, 'movimientos', $this->movementData());
        $this->actingAs($this->owner)->put('/cuentas/'.$this->account->id, $this->accountData(['saldo_inicial' => '150.00']))->assertSessionHasErrors('saldo_inicial');
        $this->put('/cuentas/'.$this->account->id, $this->accountData(['fecha_inicial' => '2026-08-01']))->assertSessionHasErrors('saldo_inicial');
        $this->assertSame('100.00', $this->account->fresh()->saldo_inicial);
    }

    public function test_movement_edits_deletion_and_account_changes_update_both_balances(): void
    {
        $second = $this->finance->save($this->owner, 'cuentas', $this->accountData(['nombre' => 'Otra', 'saldo_inicial' => '50.00']));
        $movement = $this->finance->save($this->owner, 'movimientos', $this->movementData());
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['cuenta_id' => $second->id, 'importe' => '20.00']), $movement->id);
        $this->assertSame('100.00', $this->account->fresh()->saldo);
        $this->assertSame('30.00', $second->fresh()->saldo);
        $this->finance->remove($this->owner, 'movimientos', $movement->id);
        $this->assertSame('50.00', $second->fresh()->saldo);
    }

    public function test_historical_negative_balances_rollback_edit_delete_and_account_changes(): void
    {
        $this->finance->save($this->owner, 'cuentas', $this->accountData(['saldo_inicial' => '0.00']), $this->account->id);
        $first = $this->finance->save($this->owner, 'movimientos', $this->movementData(['tipo' => 'ingreso', 'categoria_id' => $this->income->id, 'importe' => '10.00', 'fecha' => '2026-10-01']));
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['importe' => '8.00', 'fecha' => '2026-10-02']));
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['tipo' => 'ingreso', 'categoria_id' => $this->income->id, 'importe' => '5.00', 'fecha' => '2026-10-03']));
        $second = $this->finance->save($this->owner, 'cuentas', $this->accountData(['nombre' => 'Otra']));
        $this->actingAs($this->owner);
        foreach ([['fecha' => '2026-10-04'], ['importe' => '4.00'], ['cuenta_id' => $second->id]] as $change) {
            $this->put('/movimientos/'.$first->id, $this->movementData(array_merge(['tipo' => 'ingreso', 'categoria_id' => $this->income->id, 'importe' => '10.00', 'fecha' => '2026-10-01'], $change)))->assertSessionHasErrors('importe');
            $this->assertSame('7.00', $this->account->fresh()->saldo);
            $this->assertSame('2026-10-01', $first->fresh()->fecha->toDateString());
            $this->assertSame($this->account->id, $first->fresh()->cuenta_id);
        }
        $this->delete('/movimientos/'.$first->id)->assertSessionHasErrors('importe');
        $this->assertDatabaseCount('movimientos', 3);
        $this->assertSame('7.00', $this->account->fresh()->saldo);
    }

    public function test_same_day_order_is_stable_by_identifier(): void
    {
        $this->finance->save($this->owner, 'cuentas', $this->accountData(['saldo_inicial' => '0.00']), $this->account->id);
        $income = $this->finance->save($this->owner, 'movimientos', $this->movementData(['tipo' => 'ingreso', 'categoria_id' => $this->income->id]));
        $this->finance->save($this->owner, 'movimientos', $this->movementData(['importe' => '8.00']));
        $this->actingAs($this->owner)->put('/movimientos/'.$income->id, $this->movementData(['fecha' => '2026-10-11', 'tipo' => 'ingreso', 'categoria_id' => $this->income->id]))->assertSessionHasErrors('importe');
        $this->assertSame('2.00', $this->account->fresh()->saldo);
    }

    public function test_category_equivalence_type_and_usage_rules(): void
    {
        $this->actingAs($this->owner)->post('/categorias', ['nombre' => '  Cómida  ', 'tipo' => 'gasto'])->assertSessionHasErrors('nombre');
        $this->post('/categorias', ['nombre' => 'Comida', 'tipo' => 'ingreso'])->assertRedirect();
        $this->finance->save($this->owner, 'movimientos', $this->movementData());
        $this->delete('/categorias/'.$this->expense->id)->assertSessionHasErrors('categoria_id');
        $this->put('/categorias/'.$this->expense->id, ['nombre' => 'Nueva', 'tipo' => 'ingreso'])->assertSessionHasErrors('tipo');
        $this->put('/categorias/'.$this->expense->id, ['nombre' => 'Alimentos', 'tipo' => 'gasto'])->assertRedirect();
        $this->assertSame('Alimentos', $this->expense->fresh()->nombre);
    }

    public function test_invalid_amounts_dates_and_category_types_are_rejected(): void
    {
        $this->actingAs($this->owner);
        foreach (['0.00', '-1.00', '1.001', '1e2', '1,00', '10000000000000.00', 10.25] as $amount) {
            $this->post('/movimientos', $this->movementData(['importe' => $amount]))->assertSessionHasErrors('importe');
        }
        foreach (['2026-08-31', '2026-10-24', '2026-02-30'] as $date) {
            $this->post('/movimientos', $this->movementData(['fecha' => $date]))->assertSessionHasErrors('fecha');
        }
        $this->post('/movimientos', $this->movementData(['categoria_id' => $this->income->id]))->assertSessionHasErrors('categoria_id');
        $this->post('/movimientos', $this->movementData(['descripcion' => str_repeat('a', 501)]))->assertSessionHasErrors('descripcion');
        $this->assertDatabaseCount('movimientos', 0);
    }

    public function test_two_users_are_isolated_in_web_and_foreign_references_are_rejected(): void
    {
        $movement = $this->finance->save($this->owner, 'movimientos', $this->movementData());
        $other = User::factory()->create();
        $this->actingAs($other);
        foreach (['cuentas' => $this->account, 'categorias' => $this->expense, 'movimientos' => $movement] as $resource => $model) {
            $this->get("/$resource/{$model->id}")->assertNotFound();
            $this->get("/$resource/{$model->id}/edit")->assertNotFound();
            $this->put("/$resource/{$model->id}", [])->assertNotFound();
            $this->delete("/$resource/{$model->id}")->assertNotFound();
        }
        $this->get('/movimientos/create')->assertDontSee('Comida')->assertDontSee('Efectivo');
        $this->post('/movimientos', $this->movementData())->assertSessionHasErrors(['cuenta_id', 'categoria_id']);
        $this->post('/cuentas', $this->accountData(['user_id' => $this->owner->id]))->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('movimientos', 1);
    }

    public function test_api_authentication_is_json_even_without_accept_header(): void
    {
        foreach (['cuentas', 'categorias', 'movimientos', 'dashboard'] as $resource) {
            $this->get('/api/'.$resource)->assertUnauthorized()->assertJsonPath('message', 'No autenticado o credenciales incorrectas.');
        }
        $this->actingAs($this->owner)->get('/api/cuentas')->assertUnauthorized();
    }

    public function test_api_crud_decimals_and_errors_match_web_rules(): void
    {
        $this->withToken($this->token());
        $this->postJson('/api/movimientos', $this->movementData())->assertCreated()->assertJsonPath('data.importe', '10.00');
        $id = Movimiento::sole()->id;
        $this->getJson('/api/cuentas/'.$this->account->id)->assertOk()->assertJsonPath('data.saldo', '90.00');
        $this->putJson('/api/movimientos/'.$id, $this->movementData(['importe' => '200.00']))->assertUnprocessable()->assertJsonValidationErrors('importe');
        $this->assertSame('90.00', $this->account->fresh()->saldo);
        $this->putJson('/api/movimientos/'.$id, $this->movementData(['importe' => '15.25']))->assertOk()->assertJsonPath('data.importe', '15.25');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.saldo_total', '84.75')->assertJsonPath('data.gastos_mes', '15.25');
        $this->deleteJson('/api/movimientos/'.$id)->assertOk();
        $this->getJson('/api/movimientos/'.$id)->assertNotFound();
        $this->getJson('/api/cuentas/'.$this->account->id)->assertJsonPath('data.saldo', '100.00');
    }

    public function test_api_hides_foreign_resources_and_rejects_related_foreign_ids(): void
    {
        $movement = $this->finance->save($this->owner, 'movimientos', $this->movementData());
        $other = User::factory()->create();
        $this->withToken($this->token($other));
        foreach (['cuentas' => $this->account, 'categorias' => $this->expense, 'movimientos' => $movement] as $resource => $model) {
            $this->getJson("/api/$resource/{$model->id}")->assertNotFound();
            $this->putJson("/api/$resource/{$model->id}", [])->assertNotFound();
            $this->deleteJson("/api/$resource/{$model->id}")->assertNotFound();
            $this->getJson('/api/'.$resource)->assertJsonPath('meta.total', 0);
        }
        $this->postJson('/api/movimientos', $this->movementData())->assertUnprocessable()->assertJsonValidationErrors(['cuenta_id', 'categoria_id']);
        $this->getJson('/api/dashboard')->assertJsonPath('data.saldo_total', '0.00')->assertJsonPath('data.gastos_mes', '0.00');
    }

    public function test_filters_pagination_and_web_links_preserve_filters(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->finance->save($this->owner, 'movimientos', $this->movementData());
        }
        $this->withToken($this->token())->getJson('/api/movimientos?tipo=gasto&per_page=2&page=2&desde=2026-10-01&hasta=2026-10-23')->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->get('/movimientos?cuenta_id='.$this->account->id.'&per_page=1')->assertOk()->assertSee('page=2', false)->assertSee('cuenta_id=', false);
        $this->get('/movimientos?desde=2026-10-23&hasta=2026-10-01')->assertSessionHasErrors('hasta');
    }

    public function test_token_issuance_expiration_and_revocation(): void
    {
        $this->owner->password = 'PruebaSegura123';
        $this->owner->save();
        $this->postJson('/api/tokens', ['email' => $this->owner->email, 'password' => 'mala', 'nombre_dispositivo' => 'Postman'])->assertUnauthorized();
        $response = $this->postJson('/api/tokens', ['email' => $this->owner->email, 'password' => 'PruebaSegura123', 'nombre_dispositivo' => 'Postman'])->assertCreated();
        $token = $response->json('data.token');
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $token]);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/cuentas')->assertOk();
        Auth::forgetGuards();
        $this->deleteJson('/api/tokens/actual')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/cuentas')->assertUnauthorized();
        $expired = $this->token(expires: now()->subMinute());
        Auth::forgetGuards();
        $this->withToken($expired)->getJson('/api/cuentas')->assertUnauthorized();
    }

    public function test_token_issuance_is_rate_limited_and_abilities_are_enforced(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/tokens', ['email' => $this->owner->email, 'password' => 'mala', 'nombre_dispositivo' => 'Prueba'])->assertUnauthorized();
        }
        $this->postJson('/api/tokens', ['email' => $this->owner->email, 'password' => 'mala', 'nombre_dispositivo' => 'Prueba'])->assertStatus(429);
        $limited = $this->owner->createToken('Sin permiso', ['otro'], now()->addHour())->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($limited)->getJson('/api/cuentas')->assertForbidden();
    }

    public function test_business_month_uses_mexico_timezone_not_utc_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 02:00:00', 'UTC'));
        $this->assertSame('2026-10-31', $this->finance->today());
        $this->assertSame('2026-10', $this->finance->dashboard($this->owner)['mes']);
    }
}
