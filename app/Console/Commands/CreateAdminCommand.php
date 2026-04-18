<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateAdminCommand extends Command
{
    protected $signature = 'user:create
        {email : Email user}
        {--name= : Nama (default: diambil dari email)}
        {--password= : Password (default: digenerate acak 12 karakter)}
        {--role=admin : admin|operator}';

    protected $description = 'Buat user baru (admin atau operator). Untuk bootstrap tanpa UI register.';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $role = (string) $this->option('role');
        $name = (string) ($this->option('name') ?: explode('@', $email)[0]);
        $password = (string) ($this->option('password') ?: \Illuminate\Support\Str::random(12));

        $validator = Validator::make([
            'email' => $email, 'name' => $name, 'password' => $password, 'role' => $role,
        ], [
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $msg) {
                $this->error($msg);
            }
            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ]);

        $this->info("User dibuat:");
        $this->line("  id       : {$user->id}");
        $this->line("  email    : {$user->email}");
        $this->line("  name     : {$user->name}");
        $this->line("  role     : {$user->role}");
        $this->line("  password : {$password}");
        $this->warn('Simpan password ini sekarang — tidak akan ditampilkan lagi.');

        return self::SUCCESS;
    }
}
