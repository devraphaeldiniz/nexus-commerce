<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedDemo extends Command
{
    protected $signature = 'nexus:demo';
    protected $description = 'Prepara dados fictícios para demonstração local';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('O seed demo é permitido apenas fora de produção.');
            return self::FAILURE;
        }

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder', '--force' => true]);
        $this->info('Dados demonstrativos carregados com sucesso.');
        return self::SUCCESS;
    }
}
