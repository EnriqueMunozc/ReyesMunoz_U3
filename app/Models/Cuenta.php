<?php

namespace App\Models;

use App\Services\AccountLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuenta extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['user_id', 'total_ingresos', 'total_gastos', 'total_recibido', 'total_enviado'];

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
        return app(AccountLedger::class)->balance($this);
    }

    public function enviadas(): HasMany
    {
        return $this->hasMany(Transferencia::class, 'origen_id');
    }

    public function recibidas(): HasMany
    {
        return $this->hasMany(Transferencia::class, 'destino_id');
    }

    public function tieneHistorial(): bool
    {
        return $this->movimientos()->exists() || $this->enviadas()->exists() || $this->recibidas()->exists();
    }
}
