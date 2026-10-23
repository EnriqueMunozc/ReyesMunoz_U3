<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuenta extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['user_id', 'total_ingresos', 'total_gastos'];

    protected $appends = ['saldo'];

    protected function casts(): array
    {
        return ['saldo_inicial' => 'decimal:2', 'fecha_inicial' => 'date:Y-m-d', 'archivada' => 'boolean'];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    public function getSaldoAttribute(): string
    {
        $income = array_key_exists('total_ingresos', $this->attributes) ? $this->attributes['total_ingresos'] : $this->movimientos()->where('tipo', 'ingreso')->sum('importe');
        $expense = array_key_exists('total_gastos', $this->attributes) ? $this->attributes['total_gastos'] : $this->movimientos()->where('tipo', 'gasto')->sum('importe');

        return bcsub(bcadd($this->saldo_inicial, (string) ($income ?? '0'), 2), (string) ($expense ?? '0'), 2);
    }
}
