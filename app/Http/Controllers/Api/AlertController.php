<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnomalyEvent;
use App\Models\GeofenceEvent;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function geofence()
    {
        $rows = GeofenceEvent::query()->orderByDesc('event_ts')->limit(100)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }

    public function anomalies()
    {
        $rows = AnomalyEvent::query()->orderByDesc('detected_at')->limit(100)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }

    public function resolveAnomaly(int $id, Request $request)
    {
        $ev = AnomalyEvent::query()->findOrFail($id);
        $ev->status = 'RESOLVED';
        $ev->resolved_at = now();
        $ev->resolved_note = (string) $request->input('note', 'Resolved from API');
        $ev->save();

        return response()->json(['data' => $ev, 'meta' => ['ok' => true]]);
    }
}
