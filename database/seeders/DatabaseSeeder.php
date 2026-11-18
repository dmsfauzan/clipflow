<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DatabaseSeeder tidak membuat akun di produksi. Gunakan `php artisan clipflow:create-owner`.');

            return;
        }

        $email = (string) env('CLIPFLOW_OWNER_EMAIL', 'owner@clipflow.test');
        $password = (string) env('CLIPFLOW_OWNER_PASSWORD', 'password');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Pemilik Clipflow',
                'password' => Hash::make($password),
            ],
        );

        if ($user->wasRecentlyCreated) {
            $this->command?->warn(sprintf('Akun lokal dibuat: %s / %s. Segera ganti password.', $email, $password));
        }
    }
}
