<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class CreateDemoData extends Command
{
    protected $signature = 'clipflow:demo';

    protected $description = 'Buat data demo terpisah dari data nyata';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Data demo tidak boleh dibuat di produksi.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->warn('Data demo diberi label pada antarmuka dan tidak dihitung sebagai statistik nyata.');

        return self::SUCCESS;
    }
}
