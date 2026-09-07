<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeExpiredRegistrations extends Command
{
    protected $signature = 'nexus:purge-expired-registrations';
    protected $description = 'Remove registros temporários de pré-cadastro cujo prazo de 10 minutos expirou.';

    public function handle(): int
    {
        $deleted = DB::table('pending_registrations')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Registros expirados removidos: {$deleted}");

        return self::SUCCESS;
    }
}
