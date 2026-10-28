<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transferencia extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['user_id', 'huella', 'operacion_id'];

    protected function casts(): array
    {
        return ['importe' => 'decimal:2', 'fecha' => 'date:Y-m-d'];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'destino_id');
    }
}
