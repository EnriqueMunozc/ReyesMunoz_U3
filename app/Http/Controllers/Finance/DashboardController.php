<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\FinanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinanceService $finance)
    {
        $data = $finance->dashboard($request->user());

        return $request->is('api/*') ? response()->json(['data' => $data]) : view('dashboard', $data);
    }
}
