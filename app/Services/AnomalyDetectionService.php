<?php

namespace App\Services;

use App\Models\AnomalyEvent;
use App\Models\TelemetryLog;
use App\Models\Tractor;
use App\Models\TractorPositionLatest;
use Illuminate\Support\Collection;

class AnomalyDetectionService
{
    /**
     * Placeholder recovery implementation; keeps API shape compatible.
     *
     * @return Collection<int, AnomalyEvent>
     */
    public function detectFromIngest(TelemetryLog $log, Tractor $tractor, TractorPositionLatest $latest): Collection
    {
        return collect();
    }
}
