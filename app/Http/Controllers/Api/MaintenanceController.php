<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MaintenanceController extends Controller
{
    public function plans(Request $request)
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tractor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $q = MaintenancePlan::query()->with(['tractor:id,name'])->orderBy('tractor_id');

        if (! empty($validated['tractor_id'])) {
            $q->where('tractor_id', (string) $validated['tractor_id']);
        }
        if (! empty($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $q->where('updated_at', '>=', $from);
        }
        if (! empty($validated['to'])) {
            $to = Carbon::parse($validated['to'])->endOfDay();
            $q->where('updated_at', '<=', $to);
        }

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

    public function records(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tractor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $q = MaintenanceRecord::query()
            ->with(['tractor:id,name'])
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('record_date');

        if (! empty($validated['tractor_id'])) {
            $q->where('tractor_id', (string) $validated['tractor_id']);
        }

        $paginator = $q->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }
}
