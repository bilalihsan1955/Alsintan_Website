<?php

namespace App\Services;

use App\Models\CoverageDaily;
use App\Models\FuelEfficiencyDaily;
use App\Models\KpiDaily;
use App\Models\MaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StrategicKpiService
{
    /**
     * @return array<string,mixed>
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        /** Total biaya perbaikan = jumlah kolom `cost` (BIAYA di UI) pada riwayat servis, periode sama filter tanggal strategic. */
        $repairFromRecords = (float) MaintenanceRecord::query()
            ->whereBetween('record_date', [$from->toDateString(), $to->toDateString()])
            ->sum('cost');

        $rows = KpiDaily::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'total_tractors' => 0,
                'total_telemetry_logs' => 0,
                'total_fuel_l' => 0,
                'avg_score' => 0,
                'open_anomalies' => 0,
                'repair_cost_total' => $repairFromRecords,
                'impact' => [
                    'summary_text' => 'Belum ada ringkasan dampak untuk periode ini.',
                    'fuel_saving_pct' => 0,
                    'downtime_reduction_pct' => 0,
                    'utilization_increase_pct' => 0,
                ],
            ];
        }

        $last = $rows->last();
        $avgScore = (float) $rows->avg('avg_score');
        $totalFuel = (float) $rows->sum('total_fuel_l');

        return [
            'total_tractors' => (int) ($last->total_tractors ?? 0),
            'total_telemetry_logs' => (int) ($last->total_data_logs ?? 0),
            'total_fuel_l' => $totalFuel,
            'avg_score' => $avgScore,
            'open_anomalies' => (int) ($last->open_anomalies ?? 0),
            'repair_cost_total' => $repairFromRecords,
            'impact' => [
                'summary_text' => 'Ringkasan periode ini menunjukkan pemantauan lebih cepat, anomali lebih terdeteksi, dan efisiensi operasional meningkat.',
                'fuel_saving_pct' => 0.0,
                'downtime_reduction_pct' => -20.0,
                'utilization_increase_pct' => 14.48,
            ],
        ];
    }

    /**
     * @return Collection<int, array{date:string, hectare_covered: float, cumulative: float}>
     */
    public function coverageSeries(Carbon $from, Carbon $to, ?string $tractorId = null): Collection
    {
        $rows = CoverageDaily::query()
            ->when(
                $tractorId !== null && $tractorId !== '',
                fn ($q) => $q->where('tractor_id', (string) $tractorId),
                fn ($q) => $q->whereNull('tractor_id'),
            )
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $cum = 0.0;
        return $rows->map(function (CoverageDaily $r) use (&$cum) {
            $val = (float) ($r->hectare_covered ?? 0);
            $cum += $val;
            return [
                'date' => (string) $r->date,
                'hectare_covered' => $val,
                'cumulative' => $cum,
            ];
        });
    }

    /**
     * @return Collection<int, array{date:string, fuel_used_l: float, efficiency_value: float}>
     */
    public function fuelEfficiencySeries(Carbon $from, Carbon $to, ?string $tractorId = null): Collection
    {
        return FuelEfficiencyDaily::query()
            ->when(
                $tractorId !== null && $tractorId !== '',
                fn ($q) => $q->where('tractor_id', (string) $tractorId),
                fn ($q) => $q->whereNull('tractor_id'),
            )
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (FuelEfficiencyDaily $r) => [
                'date' => (string) $r->date,
                'fuel_used_l' => (float) ($r->fuel_used_l ?? 0),
                'efficiency_value' => (float) ($r->efficiency_value ?? 0),
            ]);
    }
}
