<?php

namespace App\Console\Commands;

use App\Models\Tractor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TractorTokenCommand extends Command
{
    protected $signature = 'tractor:token
        {id : ID traktor}
        {--revoke : Hapus token yang ada (tanpa membuat baru)}';

    protected $description = 'Generate / rotasi / revoke API token untuk traktor (device IoT).';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        /** @var Tractor|null $tractor */
        $tractor = Tractor::query()->find($id);
        if (! $tractor) {
            $this->error("Traktor '{$id}' tidak ditemukan");
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $tractor->forceFill(['api_token_hash' => null])->save();
            $this->info("Token traktor '{$id}' dihapus.");
            return self::SUCCESS;
        }

        /* Generate token plaintext: prefix + 48 char random. Simpan hashnya saja. */
        $plain = 'dt_'.Str::random(48);
        $tractor->forceFill(['api_token_hash' => hash('sha256', $plain)])->save();

        $this->info("Token baru untuk traktor '{$id}':");
        $this->line($plain);
        $this->warn('Tempel token ini ke firmware sekarang — plaintext tidak disimpan di server.');
        $this->line('Contoh header: X-Device-Token: '.$plain);

        return self::SUCCESS;
    }
}
