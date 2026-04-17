<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_zone_tractor', function (Blueprint $table) {
            $table->foreignId('zone_id')->constrained('geofence_zones')->cascadeOnDelete();
            $table->string('tractor_id');
            $table->timestamps();

            $table->primary(['zone_id', 'tractor_id']);
            $table->foreign('tractor_id')->references('id')->on('tractors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_zone_tractor');
    }
};
