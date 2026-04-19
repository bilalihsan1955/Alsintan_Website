<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupPerformanceScore;
use App\Services\StrategicKpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StrategicController extends Controller
{
    public function __construct(private readonly StrategicKpiService $kpiService) {}

    public function kpi(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $overview = $this->kpiService->overview($from, $to);
        $impact = $overview['impact'] ?? [];

        $data = [
            'total_alsintan' => (int) ($overview['total_tractors'] ?? 0),
            'total_data_log' => (int) ($overview['total_telemetry_logs'] ?? 0),
            'total_fuel_liters' => (float) ($overview['total_fuel_l'] ?? 0),
            'average_score' => (float) ($overview['avg_score'] ?? 0),
            'anomalies_unresolved' => (int) ($overview['open_anomalies'] ?? 0),
            'total_repair_cost_idr' => (float) ($overview['repair_cost_total'] ?? 0),
            'impact' => [
                'fuel_savings_percent' => (float) ($impact['fuel_saving_pct'] ?? 0),
                'productivity_up_percent' => (float) ($impact['utilization_increase_pct'] ?? 0),
                'downtime_down_percent' => abs((float) ($impact['downtime_reduction_pct'] ?? 0)),
            ],
        ];

        return response()->json([
            'data' => $data,
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    public function coverage(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tractor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $tractorId = isset($validated['tractor_id']) ? (string) $validated['tractor_id'] : null;

        $series = $this->kpiService->coverageSeries($from, $to, $tractorId)->values()->all();

        return response()->json([
            'data' => $series,
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'tractor_id' => $tractorId,
                'count' => count($series),
            ],
        ]);
    }

    public function fuelEfficiency(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tractor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $tractorId = isset($validated['tractor_id']) ? (string) $validated['tractor_id'] : null;

        $series = $this->kpiService->fuelEfficiencySeries($from, $to, $tractorId)->values()->all();

        return response()->json([
            'data' => $series,
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'tractor_id' => $tractorId,
                'count' => count($series),
            ],
        ]);
    }

    public function groupPerformance(Request $request)
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $q = GroupPerformanceScore::query()->with(['group:id,name,village']);

        if (! empty($validated['group_id'])) {
            $q->where('group_id', (int) $validated['group_id']);
        }

        if (! empty($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $q->where('updated_at', '>=', $from);
        }
        if (! empty($validated['to'])) {
            $to = Carbon::parse($validated['to'])->endOfDay();
            $q->where('updated_at', '<=', $to);
        }

        $q->orderByDesc('period')->orderByDesc('total_score');

        $paginator = $q->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
        ]);
    }
}
