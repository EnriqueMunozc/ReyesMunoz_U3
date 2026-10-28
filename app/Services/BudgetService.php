<?php

namespace App\Services;

use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public const MONTH = '/^(?:19|20|21)\d{2}-(?:0[1-9]|1[0-2])$/D';

    public function find(User $user, string|int $id): Presupuesto
    {
        return Presupuesto::where('user_id', $user->id)->with('categoria')->findOrFail($id);
    }

    public function listing(User $user, array $input)
    {
        $filters = Validator::make($input, [
            'mes' => ['nullable', 'string', 'regex:'.self::MONTH],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('user_id', $user->id)->where('tipo', 'gasto')],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ], ['mes.regex' => 'Selecciona un mes válido entre 1900-01 y 2199-12.'])->validate();

        return Presupuesto::where('user_id', $user->id)->with('categoria')
            ->when(! empty($filters['mes']), fn ($q) => $q->where('mes', $filters['mes']))
            ->when(! empty($filters['categoria_id']), fn ($q) => $q->where('categoria_id', $filters['categoria_id']))
            ->orderByDesc('mes')->orderByDesc('id')->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function save(User $user, array $input, string|int|null $id = null): Presupuesto
    {
        try {
            return DB::transaction(function () use ($user, $input, $id) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $model = $id === null ? new Presupuesto : $this->find($user, $id);
                $data = Validator::make($input, [
                    'user_id' => ['prohibited'],
                    'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('user_id', $user->id)->where('tipo', 'gasto')],
                    'mes' => ['required', 'string', 'regex:'.self::MONTH],
                    'limite' => ['bail', 'required', 'string', 'max:16', 'regex:/^(?:0|[1-9]\d{0,12})(?:\.\d{1,2})?$/D'],
                ], ['mes.regex' => 'Selecciona un mes válido entre 1900-01 y 2199-12.'])->validate();
                if (bccomp($data['limite'], '0', 2) <= 0) {
                    throw ValidationException::withMessages(['limite' => 'El límite debe ser mayor que cero.']);
                }
                $data['limite'] = bcadd($data['limite'], '0', 2);
                $model->user_id = $user->id;
                $model->fill($data)->save();

                return $this->find($user, $model->id);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['mes' => 'Ya existe un presupuesto para esta categoría y mes.']);
        }
    }

    public function remove(User $user, string|int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->find($user, $id)->delete();
        }, 3);
    }
}
