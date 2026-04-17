<?php

namespace App\Http\Controllers;

use App\Models\Tractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TractorManagementController extends Controller
{
    /**
     * Daftar perangkat (traktor) terintegrasi + ringkasan data.
     */
    public function index(): View
    {
        $tractors = Tractor::query()
            ->withCount(['telemetryLogs'])
            ->withMax('telemetryLogs as last_telemetry_at', 'ts')
            ->with('latestPosition')
            ->orderBy('id')
            ->get();

        return view('tractors.manage', [
            'tractors' => $tractors,
        ]);
    }

    /**
     * Perbarui nama tampilan perangkat (device_id tetap dari perangkat keras).
     */
    public function update(Request $request, Tractor $tractor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:60'],
        ]);

        $tractor->name = $validated['name'] ?? null;
        $tractor->plate_number = $validated['plate_number'] ?? null;
        $tractor->save();

        return redirect()
            ->route('tractors.manage')
            ->with('status', 'Perangkat '.$tractor->id.' berhasil diperbarui.');
    }
}
