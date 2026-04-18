<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kolom tambahan untuk profil user & preferensi (mobile + web). */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'role')) {
                /* Role minimalis: admin (CRUD strategis) / operator (baca + kirim telemetri). */
                $table->string('role', 16)->default('operator')->after('avatar_path');
            }
            if (! Schema::hasColumn('users', 'preferences')) {
                /* JSON: { theme_mode: system|light|dark, language: id|en, ... } */
                $table->json('preferences')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_values(array_filter(['phone', 'avatar_path', 'role', 'preferences'], fn ($c) => Schema::hasColumn('users', $c)));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
