<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus semua baris data domain (traktor, telemetri, zona, KPI, cache, job, …)
 * kecuali tabel yang terkait akun / sesi pengguna.
 */
class PurgeNonUserDataCommand extends Command
{
    protected $signature = 'db:purge-non-user-data
        {--force : Jalankan tanpa konfirmasi}';

    protected $description = 'Kosongkan semua tabel kecuali users, sessions, password_reset_tokens, refresh_tokens, email_otps, migrations';

    /** @var list<string> */
    private const KEEP_TABLES = [
        'migrations',
        'users',
        'password_reset_tokens',
        'sessions',
        'refresh_tokens',
        'email_otps',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Yakin hapus semua data non-user?')) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        $driver = DB::getDriverName();
        $all = $this->allTableNames($driver);

        $toClear = array_values(array_filter($all, fn (string $t) => ! in_array($t, self::KEEP_TABLES, true)));

        if ($toClear === []) {
            $this->warn('Tidak ada tabel yang dikosongkan.');

            return self::SUCCESS;
        }

        $this->info('Mengosongkan '.count($toClear).' tabel (mempertahankan: '.implode(', ', self::KEEP_TABLES).').');

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($toClear as $table) {
                DB::table($table)->delete();
                $this->line("  <fg=gray>{$table}</>");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($driver === 'sqlite') {
            $this->resetSqliteSequences($toClear);
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function allTableNames(string $driver): array
    {
        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            return array_map(fn ($r) => (string) $r->name, $rows);
        }

        $dbName = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'',
            [$dbName]
        );

        return array_map(fn ($r) => (string) $r->name, $rows);
    }

    /**
     * @param  list<string>  $clearedTables
     */
    private function resetSqliteSequences(array $clearedTables): void
    {
        if (! Schema::hasTable('sqlite_sequence')) {
            return;
        }

        foreach ($clearedTables as $name) {
            try {
                DB::table('sqlite_sequence')->where('name', $name)->delete();
            } catch (\Throwable) {
                /* abaikan tabel tanpa autoincrement */
            }
        }
    }
}
