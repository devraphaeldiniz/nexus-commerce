<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// 1. Localiza ou cria o vendedor de teste
$user = User::updateOrCreate(
    ['email' => 'vendedor.teste@nexus.com'],
    [
        'name' => 'Vendedor Teste Nexus',
        'password' => Hash::make('password123'),
        'role' => 'SELLER'
    ]
);

$plainToken = 'NEXUS_DEV_SELLER_TOKEN_2026_TEST';

// 2. Grava remember_token
$user->remember_token = $plainToken;
$user->save();

// 3. Garante tabela personal_access_tokens
DB::statement("
    CREATE TABLE IF NOT EXISTS personal_access_tokens (
        id bigserial PRIMARY KEY,
        tokenable_type varchar(255) NOT NULL,
        tokenable_id bigint NOT NULL,
        name varchar(255) NOT NULL,
        token varchar(64) NOT NULL UNIQUE,
        abilities text,
        last_used_at timestamp(0) without time zone,
        expires_at timestamp(0) without time zone,
        created_at timestamp(0) without time zone,
        updated_at timestamp(0) without time zone
    )
");

// 4. Grava na tabela de tokens
DB::table('personal_access_tokens')->updateOrInsert(
    ['tokenable_id' => $user->id, 'name' => 'nexus_api'],
    [
        'tokenable_type' => get_class($user),
        'token' => hash('sha256', $plainToken),
        'abilities' => '["*"]',
        'updated_at' => now(),
        'created_at' => now(),
    ]
);

// 5. Garante perfil e carteira do vendedor
$profile = DB::table('seller_profiles')->where('user_id', $user->id)->first();
if (!$profile) {
    $profileId = (string) Str::uuid();
    DB::table('seller_profiles')->insert([
        'id' => $profileId,
        'user_id' => $user->id,
        'store_name' => 'Nexus Store Oficial',
        'legal_name' => 'Nexus Store Oficial Comércio LTDA',
        'document_type' => 'CNPJ',
        'document_number' => '99.888.777/0001-66',
        'kyc_status' => 'APPROVED',
        'reputation_score' => 5.00,
        'total_sales_count' => 0,
        'cancellation_rate' => 0.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
} else {
    $profileId = $profile->id;
}

if (DB::getSchemaBuilder()->hasTable('seller_wallets')) {
    DB::table('seller_wallets')->updateOrInsert(
        ['seller_profile_id' => $profileId],
        [
            'id' => (string) Str::uuid(),
            'balance_available_cents' => 145000,
            'balance_escrow_cents' => 38900,
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );
}

echo PHP_EOL . ">>> SUCESSO ABSOLUTO: Vendedor sincronizado!" . PHP_EOL;
echo ">>> Email: " . $user->email . PHP_EOL;
echo ">>> Token Dev: " . $plainToken . PHP_EOL;
