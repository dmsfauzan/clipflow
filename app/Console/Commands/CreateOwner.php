<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateOwner extends Command
{
    protected $signature = 'clipflow:create-owner {--email= : Email pemilik} {--name= : Nama pemilik} {--password= : Password pemilik (disarankan memakai input interaktif)}';

    protected $description = 'Buat akun pemilik aplikasi (tidak ada registrasi publik)';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?? 'owner@clipflow.test');
        $name = (string) ($this->option('name') ?? 'Pemilik Clipflow');

        $password = (string) $this->option('password');

        if ($password === '') {
            $password = (string) $this->secret('Password pemilik');
        }

        if (mb_strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $this->info(sprintf('Akun pemilik dibuat: %s (ID %d).', $user->email, $user->id));

        if ($this->option('password') === null) {
            $this->line('Catat password ini; itu hanya ditampilkan sekali.');
        }

        return self::SUCCESS;
    }
}
