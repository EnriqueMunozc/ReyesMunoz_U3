<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransferenciaController extends Controller
{
    public function __construct(private TransferService $service) {}

    public function index(Request $request)
    {
        $items = $this->service->listing($request->user(), $request->query());

        return $request->is('api/*') ? response()->json(['data' => $items->items(), 'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage()]])
            : view('transferencias.index', ['items' => $items] + app(FinanceService::class)->options($request->user()));
    }

    public function create(Request $request)
    {
        return view('transferencias.create', ['clave' => (string) Str::uuid()] + app(FinanceService::class)->options($request->user()));
    }

    public function store(Request $request)
    {
        $result = $this->service->create($request->user(), $request->all());
        $message = $result['creada'] ? 'Transferencia registrada correctamente.' : 'La transferencia ya estaba registrada. No se duplicó la operación.';

        return $request->is('api/*') ? response()->json(['data' => $result['transferencia'], 'message' => $message, 'reutilizada' => ! $result['creada']], $result['creada'] ? 201 : 200)
            : redirect()->route('transferencias.show', $result['transferencia']->id)->with('status', $message);
    }

    public function show(Request $request, string $id)
    {
        $item = $this->service->find($request->user(), $id);

        return $request->is('api/*') ? response()->json(['data' => $item]) : view('transferencias.show', compact('item'));
    }
}
