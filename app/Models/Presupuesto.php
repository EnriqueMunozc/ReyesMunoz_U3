<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Presupuesto extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected $hidden = ['user_id'];

    protected $appends = ['indicadores'];

    protected function casts(): array
    {
        return ['limite' => 'decimal:2'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function getIndicadoresAttribute(): array
    {
        $start = Carbon::createFromFormat('!Y-m-d', $this->mes.'-01', config('app.business_timezone'));
        $spent = bcadd((string) Movimiento::where('user_id', $this->user_id)->where('categoria_id', $this->categoria_id)->where('tipo', 'gasto')
            ->whereBetween('fecha', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->sum('importe'), '0', 2);
        $difference = bcsub($this->limite, $spent, 2);
        $percent = bcadd(bcdiv(bcmul($spent, '100', 4), $this->limite, 4), '0.005', 2);

        return ['consumo' => $spent, 'disponible' => bccomp($difference, '0', 2) < 0 ? '0.00' : $difference,
            'exceso' => bccomp($difference, '0', 2) < 0 ? bcsub('0', $difference, 2) : '0.00',
            'porcentaje_utilizado' => $percent, 'ancho_barra' => bccomp($percent, '100', 2) > 0 ? '100.00' : $percent];
    }
}
