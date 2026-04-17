<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoverageDaily;
use App\Models\FuelEfficiencyDaily;
use App\Models\GroupPerformanceScore;
use App\Models\KpiDaily;
use Illuminate\Http\Request;

class StrategicController extends Controller
{
    public function kpi(Request $request)
    {
        $rows = KpiDaily::query()->orderByDesc('date')->limit(30)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }

    public function coverage(Request $request)
    {
        $rows = CoverageDaily::query()->whereNull('tractor_id')->orderByDesc('date')->limit(30)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }

    public function fuelEfficiency(Request $request)
    {
        $rows = FuelEfficiencyDaily::query()->whereNull('tractor_id')->orderByDesc('date')->limit(30)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }

    public function groupPerformance(Request $request)
    {
        $rows = GroupPerformanceScore::query()->orderByDesc('period')->orderByDesc('total_score')->limit(100)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }
}
