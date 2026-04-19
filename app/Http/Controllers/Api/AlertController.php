<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnomalyEvent;
use App\Models\GeofenceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AlertController extends Controller
{
    public function geofence(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tractor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'zone_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'event_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $q = GeofenceEvent::query()
            ->with(['tractor:id,name', 'zone:id,name'])
            ->whereBetween('event_ts', [$from, $to])
            ->orderByDesc('event_ts');

        if (! empty($validated['tractor_id'])) {
            $q->where('tractor_id', (string) $validated['tractor_id']);
        }
        if (! empty($validated['zone_id'])) {
            $q->where('zone_id', (int) $validated['zone_id']);
        }
        if (! empty($validated['event_type'])) {
            $q->where('event_type', (string) $validated['event_type']);
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

    public function anomalies(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'tractor_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'severity' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $q = AnomalyEvent::query()
            ->with(['tractor:id,name'])
            ->whereBetween('detected_at', [$from, $to])
            ->orderByDesc('detected_at');

        if (! empty($validated['tractor_id'])) {
            $q->where('tractor_id', (string) $validated['tractor_id']);
        }
        if (! empty($validated['severity'])) {
            $q->whereRaw('lower(severity) = ?', [strtolower((string) $validated['severity'])]);
        }
        if (! empty($validated['status'])) {
            $q->whereRaw('lower(status) = ?', [strtolower((string) $validated['status'])]);
        }

        $paginator = $q->paginate($perPage)->appends($request->query());

        $data = collect($paginator->items())->map(function (AnomalyEvent $row) {
            $arr = $row->toArray();
            if (isset($arr['severity'])) {
                $arr['severity'] = strtolower((string) $arr['severity']);
            }
            if (isset($arr['status'])) {
                $arr['status'] = strtolower((string) $arr['status']);
            }

            return $arr;
        })->values()->all();

        return response()->json([
            'data' => $data,
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

    public function resolveAnomaly(int $id, Request $request)
    {
        $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $ev = AnomalyEvent::query()->findOrFail($id);
        $ev->status = 'RESOLVED';
        $ev->resolved_at = now();
        $ev->resolved_note = (string) $request->input('note', 'Resolved from API');
        $ev->save();

        $fresh = $ev->fresh();
        $arr = $fresh?->toArray() ?? [];
        if (isset($arr['severity'])) {
            $arr['severity'] = strtolower((string) $arr['severity']);
        }
        if (isset($arr['status'])) {
            $arr['status'] = strtolower((string) $arr['status']);
        }

        return response()->json([
            'data' => $arr,
            'meta' => ['ok' => true],
        ]);
    }
}
