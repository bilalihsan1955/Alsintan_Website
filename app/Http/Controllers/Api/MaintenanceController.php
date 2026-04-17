<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function plans(Request $request)
    {
        $rows = MaintenancePlan::query()->orderBy('tractor_id')->limit(200)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }

    public function records(Request $request)
    {
        $rows = MaintenanceRecord::query()->orderByDesc('record_date')->limit(200)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }
}
