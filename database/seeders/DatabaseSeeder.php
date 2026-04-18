<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Akun demo (lokal saja — jangan pakai di produksi):
     * - admin@alsintan.id    | password123 | role admin
     * - operator@alsintan.id | password123 | role operator
     */
    public function run(): void
    {
        $plain = 'password123';

        User::query()->updateOrCreate(
            ['email' => 'admin@alsintan.id'],
            [
                'name' => 'Admin Alsintan',
                'password' => $plain,
                'role' => 'admin',
                'preferences' => ['theme_mode' => 'system', 'language' => 'id'],
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'operator@alsintan.id'],
            [
                'name' => 'Operator Demo',
                'password' => $plain,
                'role' => 'operator',
                'preferences' => ['theme_mode' => 'system', 'language' => 'id'],
            ],
        );
    }
}
