<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['user_id', 'nombre_clave'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class);
    }
}
