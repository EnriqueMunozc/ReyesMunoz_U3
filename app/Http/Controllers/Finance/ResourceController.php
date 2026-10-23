<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use Illuminate\Http\Request;

abstract class ResourceController extends Controller
{
    protected string $resource;

    public function __construct(protected FinanceService $finance) {}

    public function index(Request $request)
    {
        $items = $this->finance->listing($request->user(), $this->resource, $request->query());
        if ($request->is('api/*')) {
            return response()->json(['data' => $items->items(), 'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()]]);
        }

        return view('finance.index', ['resource' => $this->resource, 'items' => $items] + $this->finance->options($request->user()));
    }

    public function create(Request $request)
    {
        return view('finance.form', ['resource' => $this->resource, 'item' => null] + $this->finance->options($request->user()));
    }

    public function store(Request $request)
    {
        $item = $this->finance->save($request->user(), $this->resource, $request->all());
        $message = $this->successMessage(true);

        return $request->is('api/*') ? response()->json(['data' => $item, 'message' => $message], 201)
            : redirect()->route($this->resource.'.show', $item->id)->with('status', $message);
    }

    public function show(Request $request, string $id)
    {
        $item = $this->finance->find($request->user(), $this->resource, $id);

        return $request->is('api/*') ? response()->json(['data' => $item])
            : view('finance.show', ['resource' => $this->resource, 'item' => $item]);
    }

    public function edit(Request $request, string $id)
    {
        $item = $this->finance->find($request->user(), $this->resource, $id);

        return view('finance.form', ['resource' => $this->resource, 'item' => $item] + $this->finance->options($request->user()));
    }

    public function update(Request $request, string $id)
    {
        $item = $this->finance->save($request->user(), $this->resource, $request->all(), $id);
        $message = $this->successMessage(false);

        return $request->is('api/*') ? response()->json(['data' => $item, 'message' => $message])
            : redirect()->route($this->resource.'.show', $id)->with('status', $message);
    }

    public function destroy(Request $request, string $id)
    {
        $message = $this->finance->remove($request->user(), $this->resource, $id);

        return $request->is('api/*') ? response()->json(['data' => null, 'message' => $message])
            : redirect()->route($this->resource.'.index')->with('status', $message);
    }

    private function successMessage(bool $created): string
    {
        $name = ['cuentas' => 'Cuenta', 'categorias' => 'Categoría', 'movimientos' => 'Movimiento'][$this->resource];
        $action = $created ? 'cread' : 'actualizad';

        return $name.' '.$action.($this->resource === 'movimientos' ? 'o' : 'a').' correctamente.';
    }
}
