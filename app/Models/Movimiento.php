<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['user_id'];

    protected function casts(): array
    {
        return ['importe' => 'decimal:2', 'fecha' => 'date:Y-m-d'];
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
