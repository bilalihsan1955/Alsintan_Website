<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas ketuaan "online" (daftar unit / peta)
    |--------------------------------------------------------------------------
    |
    | Jika tidak ada telemetri/posisi lebih baru dari nilai ini (menit),
    | field connection_status = offline.
    |
    */
    'fleet_online_stale_minutes' => (int) env('ALSINTAN_FLEET_ONLINE_STALE_MINUTES', 15),

];
