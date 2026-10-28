<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\Transferencia;
use Illuminate\Validation\ValidationException;

class AccountLedger
{
    public function balance(Cuenta $account): string
    {
        $attributes = $account->getAttributes();
        $sum = fn ($key, $relation, $type = null) => array_key_exists($key, $attributes) ? (string) ($attributes[$key] ?? '0')
            : (string) ($type === null ? $account->$relation()->sum('importe') : $account->$relation()->where('tipo', $type)->sum('importe'));
        $credit = bcadd($sum('total_ingresos', 'movimientos', 'ingreso'), $sum('total_recibido', 'recibidas'), 2);
        $debit = bcadd($sum('total_gastos', 'movimientos', 'gasto'), $sum('total_enviado', 'enviadas'), 2);

        return bcsub(bcadd($account->saldo_inicial, $credit, 2), $debit, 2);
    }

    public function assertHistory(Cuenta $account): void
    {
        $events = [];
        foreach (Movimiento::where('cuenta_id', $account->id)->get() as $row) {
            $events[] = [$row->fecha->toDateString(), $row->operacion_id, $row->tipo === 'ingreso' ? $row->importe : bcsub('0', $row->importe, 2)];
        }
        foreach (Transferencia::where(fn ($q) => $q->where('origen_id', $account->id)->orWhere('destino_id', $account->id))->get() as $row) {
            $events[] = [$row->fecha->toDateString(), $row->operacion_id, $row->destino_id === $account->id ? $row->importe : bcsub('0', $row->importe, 2)];
        }
        usort($events, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $balance = $account->saldo_inicial;
        foreach ($events as $event) {
            $balance = bcadd($balance, $event[2], 2);
            if (bccomp($balance, '0', 2) < 0) {
                throw ValidationException::withMessages(['importe' => 'La operación dejaría un saldo negativo en el historial de una cuenta. No se guardó ningún cambio.']);
            }
            if (bccomp($balance, '9999999999999.99', 2) > 0) {
                throw ValidationException::withMessages(['importe' => 'El saldo de la cuenta supera el máximo permitido.']);
            }
        }
    }
}
