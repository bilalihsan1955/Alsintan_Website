<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UtilizationDaily;
use Illuminate\Http\Request;

class UtilizationController extends Controller
{
    public function index(Request $request)
    {
        $rows = UtilizationDaily::query()->orderByDesc('date')->orderBy('tractor_id')->limit(200)->get();
        return response()->json(['data' => $rows, 'meta' => ['count' => $rows->count()]]);
    }
}
