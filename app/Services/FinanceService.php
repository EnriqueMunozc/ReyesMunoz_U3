<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public const ACCOUNT_TYPES = ['efectivo', 'bancaria', 'ahorro', 'otra'];

    private const MODELS = ['cuentas' => Cuenta::class, 'categorias' => Categoria::class, 'movimientos' => Movimiento::class];

    public function today(): string
    {
        return now(config('app.business_timezone'))->toDateString();
    }

    public function query(User $user, string $resource): Builder
    {
        $query = self::MODELS[$resource]::query()->where('user_id', $user->id);
        if ($resource === 'cuentas') {
            $query->withSum(['movimientos as total_ingresos' => fn ($q) => $q->where('tipo', 'ingreso')], 'importe')
                ->withSum(['movimientos as total_gastos' => fn ($q) => $q->where('tipo', 'gasto')], 'importe');
        }
        if ($resource === 'movimientos') {
            $query->with(['cuenta', 'categoria']);
        }

        return $query;
    }

    public function find(User $user, string $resource, string|int $id): Model
    {
        return $this->query($user, $resource)->findOrFail($id);
    }

    public function listing(User $user, string $resource, array $input)
    {
        $rules = ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']];
        if ($resource === 'movimientos') {
            $rules += [
                'cuenta_id' => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('user_id', $user->id)],
                'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('user_id', $user->id)],
                'tipo' => ['nullable', Rule::in(['ingreso', 'gasto'])],
                'desde' => ['nullable', 'date_format:Y-m-d'],
                'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            ];
            // Una fecha final sin fecha inicial también es válida.
            if (empty($input['desde'])) {
                $rules['hasta'] = ['nullable', 'date_format:Y-m-d'];
            }
        }
        $filters = Validator::make($input, $rules)->validate();
        $query = $this->query($user, $resource);
        if ($resource === 'movimientos') {
            foreach (['cuenta_id', 'categoria_id', 'tipo'] as $key) {
                if (! empty($filters[$key])) {
                    $query->where($key, $filters[$key]);
                }
            }
            if (! empty($filters['desde'])) {
                $query->where('fecha', '>=', $filters['desde']);
            }
            if (! empty($filters['hasta'])) {
                $query->where('fecha', '<=', $filters['hasta']);
            }
            $query->orderByDesc('fecha')->orderByDesc('id');
        } else {
            $query->orderBy('nombre')->orderBy('id');
        }

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function options(User $user): array
    {
        return [
            'cuentas' => $this->query($user, 'cuentas')->orderBy('nombre')->get(),
            'categorias' => $this->query($user, 'categorias')->orderBy('tipo')->orderBy('nombre')->get(),
            'hoy' => $this->today(),
        ];
    }

    public function save(User $user, string $resource, array $input, string|int|null $id = null): Model
    {
        try {
            return DB::transaction(function () use ($user, $resource, $input, $id) {
                // Serializa las escrituras del mismo propietario, incluso categorías y archivo.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $model = $id === null ? new (self::MODELS[$resource]) : $this->find($user, $resource, $id);
                $data = $this->validate($user, $resource, $input);
                $model->user_id = $user->id;
                if ($resource === 'cuentas') {
                    if ($model->exists) {
                        Cuenta::whereKey($model->id)->lockForUpdate()->firstOrFail();
                        if ($model->movimientos()->exists()
                            && (bccomp($model->saldo_inicial, $data['saldo_inicial'], 2) !== 0 || $model->fecha_inicial->toDateString() !== $data['fecha_inicial'])) {
                            $this->invalid('saldo_inicial', 'El saldo y la fecha inicial no pueden cambiar porque la cuenta ya tiene movimientos.');
                        }
                    }
                    $data['saldo_inicial'] = bcadd($data['saldo_inicial'], '0', 2);
                } elseif ($resource === 'categorias') {
                    if ($model->exists && $model->tipo !== $data['tipo'] && $model->movimientos()->exists()) {
                        $this->invalid('tipo', 'No puedes cambiar el tipo de una categoría utilizada.');
                    }
                    $data['nombre'] = Str::squish($data['nombre']);
                    $data['nombre_clave'] = hash('sha256', Str::lower(Str::transliterate($data['nombre'])));
                } else {
                    $ids = array_unique(array_filter([$model->cuenta_id, $data['cuenta_id']]));
                    $accounts = Cuenta::where('user_id', $user->id)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    $account = $accounts->get($data['cuenta_id']);
                    $category = Categoria::where('user_id', $user->id)->findOrFail($data['categoria_id']);
                    if ($account->archivada) {
                        $this->invalid('cuenta_id', 'Esta cuenta está archivada. Reactívala antes de registrar o editar movimientos.');
                    }
                    if ($data['fecha'] < $account->fecha_inicial->toDateString()) {
                        $this->invalid('fecha', 'La fecha no puede ser anterior a la fecha inicial de la cuenta.');
                    }
                    if ($category->tipo !== $data['tipo']) {
                        $this->invalid('categoria_id', 'La categoría debe corresponder al tipo de movimiento.');
                    }
                    $data['importe'] = bcadd($data['importe'], '0', 2);
                }
                $model->fill($data)->save();
                if ($resource === 'movimientos') {
                    foreach ($accounts as $account) {
                        $this->assertHistory($account);
                    }
                }

                return $this->find($user, $resource, $model->id);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->invalid('nombre', 'Ya existe una categoría equivalente del mismo tipo.');
        }
    }

    private function validate(User $user, string $resource, array $input): array
    {
        if (isset($input['nombre']) && is_string($input['nombre'])) {
            $input['nombre'] = Str::squish($input['nombre']);
        }
        $money = ['required', 'string', 'regex:/^(?:0|[1-9]\d{0,12})(?:\.\d{1,2})?$/D'];
        $rules = match ($resource) {
            'cuentas' => [
                'nombre' => ['required', 'string', 'max:100'], 'tipo' => ['required', Rule::in(self::ACCOUNT_TYPES)],
                'saldo_inicial' => $money, 'fecha_inicial' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
                'archivada' => ['sometimes', 'boolean'],
            ],
            'categorias' => ['nombre' => ['required', 'string', 'max:100'], 'tipo' => ['required', Rule::in(['ingreso', 'gasto'])]],
            'movimientos' => [
                'cuenta_id' => ['required', 'integer', Rule::exists('cuentas', 'id')->where('user_id', $user->id)],
                'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('user_id', $user->id)],
                'tipo' => ['required', Rule::in(['ingreso', 'gasto'])], 'importe' => $money,
                'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
                'descripcion' => ['nullable', 'string', 'max:500'],
            ],
        };
        $data = Validator::make($input, $rules + ['user_id' => ['prohibited']])->validate();
        if ($resource === 'movimientos' && bccomp($data['importe'], '0', 2) <= 0) {
            $this->invalid('importe', 'El importe debe ser mayor que cero.');
        }

        return $data;
    }

    public function remove(User $user, string $resource, string|int $id): string
    {
        return DB::transaction(function () use ($user, $resource, $id) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $model = $this->find($user, $resource, $id);
            if ($resource === 'categorias' && $model->movimientos()->exists()) {
                $this->invalid('categoria_id', 'No puedes eliminar una categoría utilizada por movimientos.');
            }
            if ($resource === 'cuentas') {
                Cuenta::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($model->movimientos()->exists()) {
                    $model->archivada = true;
                    $model->save();

                    return 'Cuenta archivada correctamente. El historial se conserva.';
                }
            }
            if ($resource === 'movimientos') {
                $account = Cuenta::where('user_id', $user->id)->whereKey($model->cuenta_id)->lockForUpdate()->firstOrFail();
            }
            $model->delete();
            if ($resource === 'movimientos') {
                $this->assertHistory($account);
            }

            return match ($resource) {
                'cuentas' => 'Cuenta eliminada correctamente.',
                'categorias' => 'Categoría eliminada correctamente.',
                'movimientos' => 'Movimiento eliminado correctamente.',
            };
        }, 3);
    }

    private function assertHistory(Cuenta $account): void
    {
        $balance = $account->saldo_inicial;
        foreach ($account->movimientos()->orderBy('fecha')->orderBy('id')->get() as $movement) {
            $balance = $movement->tipo === 'ingreso' ? bcadd($balance, $movement->importe, 2) : bcsub($balance, $movement->importe, 2);
            if (bccomp($balance, '0', 2) < 0) {
                $this->invalid('importe', 'La operación dejaría un saldo negativo en el historial de una cuenta. No se guardó ningún cambio.');
            }
        }
        if (bccomp($balance, '9999999999999.99', 2) > 0) {
            $this->invalid('importe', 'El saldo de la cuenta supera el máximo permitido.');
        }
    }

    public function dashboard(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $month = now(config('app.business_timezone'))->startOfMonth();
            $accounts = $this->query($user, 'cuentas')->orderBy('nombre')->get();
            $balance = '0.00';
            foreach ($accounts->where('archivada', false) as $account) {
                $balance = bcadd($balance, $account->saldo, 2);
            }
            $movements = Movimiento::where('user_id', $user->id)->whereBetween('fecha', [$month->toDateString(), $this->today()]);
            $income = bcadd((string) (clone $movements)->where('tipo', 'ingreso')->sum('importe'), '0', 2);
            $expense = bcadd((string) (clone $movements)->where('tipo', 'gasto')->sum('importe'), '0', 2);

            return ['saldo_total' => $balance, 'ingresos_mes' => $income, 'gastos_mes' => $expense,
                'balance_mes' => bcsub($income, $expense, 2), 'mes' => $month->format('Y-m'),
                'zona_horaria' => config('app.business_timezone'), 'cuentas' => $accounts,
                'ultimos_movimientos' => $this->query($user, 'movimientos')->orderByDesc('fecha')->orderByDesc('id')->limit(5)->get()];
        });
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
