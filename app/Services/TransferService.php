<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Transferencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public function find(User $user, string|int $id): Transferencia
    {
        return Transferencia::where('user_id', $user->id)->with(['origen', 'destino'])->findOrFail($id);
    }

    public function listing(User $user, array $input)
    {
        $filters = Validator::make($input, [
            'cuenta_id' => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('user_id', $user->id)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...(! empty($input['desde']) ? ['after_or_equal:desde'] : [])],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ])->validate();

        return Transferencia::where('user_id', $user->id)->with(['origen', 'destino'])
            ->when(! empty($filters['cuenta_id']), fn ($q) => $q->where(fn ($q) => $q->where('origen_id', $filters['cuenta_id'])->orWhere('destino_id', $filters['cuenta_id'])))
            ->when(! empty($filters['desde']), fn ($q) => $q->where('fecha', '>=', $filters['desde']))
            ->when(! empty($filters['hasta']), fn ($q) => $q->where('fecha', '<=', $filters['hasta']))
            ->orderByDesc('fecha')->orderByDesc('operacion_id')->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function create(User $user, array $input): array
    {
        return DB::transaction(function () use ($user, $input) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $data = Validator::make($input, [
                'user_id' => ['prohibited'],
                'origen_id' => ['required', 'integer', Rule::exists('cuentas', 'id')->where('user_id', $user->id)],
                'destino_id' => ['required', 'integer', 'different:origen_id', Rule::exists('cuentas', 'id')->where('user_id', $user->id)],
                'importe' => ['bail', 'required', 'string', 'max:16', 'regex:/^(?:0|[1-9]\d{0,12})(?:\.\d{1,2})?$/D'],
                'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.app(FinanceService::class)->today()],
                'descripcion' => ['nullable', 'string', 'max:500'], 'clave_idempotencia' => ['required', 'uuid'],
            ])->validate();
            if (bccomp($data['importe'], '0', 2) <= 0) {
                throw ValidationException::withMessages(['importe' => 'El importe debe ser mayor que cero.']);
            }
            $data['importe'] = bcadd($data['importe'], '0', 2);
            $data['origen_id'] = (int) $data['origen_id'];
            $data['destino_id'] = (int) $data['destino_id'];
            $data['descripcion'] = isset($data['descripcion']) && trim($data['descripcion']) !== '' ? trim($data['descripcion']) : null;
            $key = strtolower($data['clave_idempotencia']);
            unset($data['clave_idempotencia'], $data['user_id']);
            $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $existing = Transferencia::where('user_id', $user->id)->where('clave_idempotencia', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->huella, $fingerprint)) {
                    throw ValidationException::withMessages(['clave_idempotencia' => 'Esta clave ya se utilizó con datos diferentes. La transferencia original se conserva.']);
                }

                return ['transferencia' => $this->find($user, $existing->id), 'creada' => false];
            }
            $accounts = Cuenta::where('user_id', $user->id)->whereIn('id', [$data['origen_id'], $data['destino_id']])->orderBy('id')->lockForUpdate()->get();
            foreach ($accounts as $account) {
                if ($account->archivada) {
                    throw ValidationException::withMessages(['origen_id' => 'Las dos cuentas deben estar activas.']);
                }
                if ($data['fecha'] < $account->fecha_inicial->toDateString()) {
                    throw ValidationException::withMessages(['fecha' => 'La fecha debe ser igual o posterior a la fecha inicial de ambas cuentas.']);
                }
            }
            $model = new Transferencia($data + ['clave_idempotencia' => $key, 'huella' => $fingerprint,
                'operacion_id' => DB::table('operaciones_financieras')->insertGetId(['created_at' => now()])]);
            $model->user_id = $user->id;
            $model->save();
            foreach ($accounts as $account) {
                app(AccountLedger::class)->assertHistory($account);
            }

            return ['transferencia' => $this->find($user, $model->id), 'creada' => true];
        }, 3);
    }
}
