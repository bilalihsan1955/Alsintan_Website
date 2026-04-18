<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** API token per-traktor (header X-Device-Token) untuk ESP32/RPi. Disimpan sebagai hash SHA-256. */
    public function up(): void
    {
        Schema::table('tractors', function (Blueprint $table) {
            if (! Schema::hasColumn('tractors', 'api_token_hash')) {
                $table->string('api_token_hash', 64)->nullable()->unique()->after('device_uid');
            }
            if (! Schema::hasColumn('tractors', 'api_token_last_used_at')) {
                $table->timestamp('api_token_last_used_at')->nullable()->after('api_token_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tractors', function (Blueprint $table) {
            foreach (['api_token_hash', 'api_token_last_used_at'] as $col) {
                if (Schema::hasColumn('tractors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
