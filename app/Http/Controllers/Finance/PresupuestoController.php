<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class PresupuestoController extends Controller
{
    public function __construct(private BudgetService $service) {}

    private function options(Request $request): array
    {
        return ['categorias' => Categoria::where('user_id', $request->user()->id)->where('tipo', 'gasto')->orderBy('nombre')->get()];
    }

    public function index(Request $request)
    {
        $items = $this->service->listing($request->user(), $request->query());

        return $request->is('api/*') ? response()->json(['data' => $items->items(), 'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage()]])
            : view('presupuestos.index', ['items' => $items] + $this->options($request));
    }

    public function create(Request $request)
    {
        return view('presupuestos.form', ['item' => null] + $this->options($request));
    }

    public function store(Request $request)
    {
        $item = $this->service->save($request->user(), $request->all());

        return $request->is('api/*') ? response()->json(['data' => $item, 'message' => 'Presupuesto creado correctamente.'], 201)
            : redirect()->route('presupuestos.show', $item->id)->with('status', 'Presupuesto creado correctamente.');
    }

    public function show(Request $request, string $id)
    {
        $item = $this->service->find($request->user(), $id);

        return $request->is('api/*') ? response()->json(['data' => $item]) : view('presupuestos.show', compact('item'));
    }

    public function edit(Request $request, string $id)
    {
        return view('presupuestos.form', ['item' => $this->service->find($request->user(), $id)] + $this->options($request));
    }

    public function update(Request $request, string $id)
    {
        $item = $this->service->save($request->user(), $request->all(), $id);

        return $request->is('api/*') ? response()->json(['data' => $item, 'message' => 'Presupuesto actualizado correctamente.'])
            : redirect()->route('presupuestos.show', $item->id)->with('status', 'Presupuesto actualizado correctamente.');
    }

    public function destroy(Request $request, string $id)
    {
        $this->service->remove($request->user(), $id);

        return $request->is('api/*') ? response()->json(['data' => null, 'message' => 'Presupuesto eliminado correctamente.'])
            : redirect()->route('presupuestos.index')->with('status', 'Presupuesto eliminado correctamente.');
    }
}
