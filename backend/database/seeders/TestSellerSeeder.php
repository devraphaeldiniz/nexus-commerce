<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestSellerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'vendedor.teste@nexus.com'],
            [
                'name'     => 'Vendedor Teste Nexus',
                'password' => Hash::make('password123'),
                'role'     => 'SELLER',
            ]
        );

        $profile = DB::table('seller_profiles')->where('user_id', $user->id)->first();
        if (! $profile) {
            $profileId = (string) Str::uuid();
            DB::table('seller_profiles')->insert([
                'id'                => $profileId,
                'user_id'           => $user->id,
                'store_name'        => 'Nexus Store Oficial',
                'legal_name'        => 'Nexus Store Oficial Comércio LTDA',
                'document_type'     => 'CNPJ',
                'document_number'   => '99.888.777/0001-66',
                'kyc_status'        => 'APPROVED',
                'reputation_score'  => 5.00,
                'total_sales_count' => 0,
                'cancellation_rate' => 0.00,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } else {
            $profileId = $profile->id;
        }

        if (DB::getSchemaBuilder()->hasTable('seller_wallets')) {
            DB::table('seller_wallets')->updateOrInsert(
                ['seller_profile_id' => $profileId],
                [
                    'id'                      => (string) Str::uuid(),
                    'balance_available_cents' => 0,
                    'balance_escrow_cents'    => 0,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]
            );
        }

        $this->command->info('>>> Vendedor criado com sucesso: vendedor.teste@nexus.com / password123');
    }
}
