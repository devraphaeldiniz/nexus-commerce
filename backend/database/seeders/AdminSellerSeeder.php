<?php

namespace Database\Seeders;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSellerSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'ADMIN')->first();
        if ($admin) {
            SellerProfile::firstOrCreate(
                ['user_id' => $admin->id],
                [
                    'store_name'      => 'Nexus Oficial Store',
                    'legal_name'      => 'Nexus Marketplace Global S.A.',
                    'document_type'   => 'CNPJ',
                    'document_number' => '00.000.000/0001-00',
                ]
            );
            $this->command->info('PERFIL_SELLER_ADMIN_CRIADO');
        } else {
            $this->command->warn('ADMIN_NAO_ENCONTRADO');
        }
    }
}
