<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceResetSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('1. Limpando base de dados...');

        DB::statement('TRUNCATE TABLE order_items CASCADE;');
        if (DB::getSchemaBuilder()->hasTable('order_escrows')) {
            DB::statement('TRUNCATE TABLE order_escrows CASCADE;');
        }
        if (DB::getSchemaBuilder()->hasTable('seller_ledgers')) {
            DB::statement('TRUNCATE TABLE seller_ledgers CASCADE;');
        }
        if (DB::getSchemaBuilder()->hasTable('wallet_transactions')) {
            DB::statement('TRUNCATE TABLE wallet_transactions CASCADE;');
        }
        if (DB::getSchemaBuilder()->hasTable('order_disputes')) {
            DB::statement('TRUNCATE TABLE order_disputes CASCADE;');
        }
        DB::statement('TRUNCATE TABLE orders CASCADE;');
        DB::statement('TRUNCATE TABLE products CASCADE;');

        $this->command->info('2. Configurando Vendedores...');

        $sellersData = [
            ['name' => 'Tech Nexus Eletrônicos', 'email' => 'vendedor.tech@nexus.com', 'doc' => '12.345.678/0001-90'],
            ['name' => 'Alpha Games & Hardware', 'email' => 'vendedor.hardware@nexus.com', 'doc' => '23.456.789/0001-01'],
            ['name' => 'Urban Style Moda', 'email' => 'vendedor.moda@nexus.com', 'doc' => '34.567.890/0001-12'],
            ['name' => 'Casa & Harmonia Decor', 'email' => 'vendedor.casa@nexus.com', 'doc' => '45.678.901/0001-23'],
            ['name' => 'Titan Áudio & Som', 'email' => 'vendedor.audio@nexus.com', 'doc' => '56.789.012/0001-34'],
        ];

        $sellerProfileIds = [];

        foreach ($sellersData as $s) {
            $user = User::updateOrCreate(
                ['email' => $s['email']],
                ['name' => $s['name'], 'password' => Hash::make('password123'), 'role' => 'SELLER']
            );

            $profile = DB::table('seller_profiles')->where('user_id', $user->id)->first();
            if (! $profile) {
                $profileId = (string) Str::uuid();
                DB::table('seller_profiles')->insert([
                    'id'                => $profileId,
                    'user_id'           => $user->id,
                    'store_name'        => $s['name'],
                    'legal_name'        => $s['name'] . ' LTDA',
                    'document_type'     => 'CNPJ',
                    'document_number'   => $s['doc'],
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

            $sellerProfileIds[] = $profileId;
        }

        $this->command->info('3. Configurando Categorias...');

        $categoriesSeed = [
            'eletronicos' => 'Smartphones & Gadgets',
            'hardware'    => 'Hardware & PCs',
            'audio'       => 'Áudio & Fones',
            'moda'        => 'Moda & Calçados',
            'casa-decor'  => 'Casa & Escritório',
        ];

        $categoryIds = [];
        foreach ($categoriesSeed as $slug => $name) {
            $cat = Category::updateOrCreate(['slug' => $slug], ['name' => $name]);
            $categoryIds[$slug] = $cat->id;
        }

        $this->command->info('4. Inserindo 1.500 Itens com Imagens Locais Infalíveis...');

        $catalogTemplates = [
            'eletronicos' => [
                'seller_index' => 0,
                'items' => [
                    [
                        'base_name'   => 'Smartphone 5G Pro Max',
                        'desc'        => 'Smartphone com tela AMOLED 120Hz, processador octa-core e câmera de 108MP.',
                        'image'       => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=500&auto=format&fit=crop&q=80',
                        'price'       => [289900, 649900],
                        'weight'      => 380, 'dim' => [16, 8, 2],
                        'modifiers'   => ['128GB Grafite', '256GB Prata', '512GB Azul Titanium'],
                    ],
                    [
                        'base_name'   => 'Smartwatch Esportivo AMOLED',
                        'desc'        => 'Relógio inteligente com monitoramento cardíaco, GPS autônomo e resistência 50m.',
                        'image'       => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500&auto=format&fit=crop&q=80',
                        'price'       => [39900, 149900],
                        'weight'      => 150, 'dim' => [12, 10, 5],
                        'modifiers'   => ['Pulseira Silicone Preto', 'Pulseira Aço Silver', 'Edição Esportiva GPS'],
                    ],
                    [
                        'base_name'   => 'Tablet Ultra Slim 11"',
                        'desc'        => 'Tablet com tela de alta resolução, suporte a caneta ativa e bateria de longa duração.',
                        'image'       => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=500&auto=format&fit=crop&q=80',
                        'price'       => [179900, 399900],
                        'weight'      => 650, 'dim' => [28, 20, 3],
                        'modifiers'   => ['Wi-Fi 128GB Cinza', 'Wi-Fi 256GB Silver', 'LTE 4G Silver'],
                    ],
                    [
                        'base_name'   => 'Drone Quadricóptero 4K HDR',
                        'desc'        => 'Drone com estabilização por gimbal de 3 eixos, alcance de 8km e retorno automático.',
                        'image'       => 'https://images.unsplash.com/photo-1508614589041-895b88991e3e?w=500&auto=format&fit=crop&q=80',
                        'price'       => [239900, 549900],
                        'weight'      => 980, 'dim' => [30, 25, 12],
                        'modifiers'   => ['Combo Fly More', 'Bateria Estendida', 'Edição Explorer 4K'],
                    ],
                ]
            ],
            'hardware' => [
                'seller_index' => 1,
                'items' => [
                    [
                        'base_name'   => 'Placa de Vídeo RTX Ray Tracing',
                        'desc'        => 'Placa gráfica dedicada com ventoinhas duplas, barramento PCIe e ray tracing.',
                        'image'       => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=500&auto=format&fit=crop&q=80',
                        'price'       => [320000, 950000],
                        'weight'      => 1450, 'dim' => [32, 14, 6],
                        'modifiers'   => ['12GB OC Dual Fan', '16GB V2 Black', '16GB White Edition OC'],
                    ],
                    [
                        'base_name'   => 'Processador Multithread High-Performance',
                        'desc'        => 'CPU de alto rendimento para jogos competitivos e renderização multitarefa.',
                        'image'       => 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=500&auto=format&fit=crop&q=80',
                        'price'       => [119900, 269900],
                        'weight'      => 250, 'dim' => [12, 12, 6],
                        'modifiers'   => ['8-Cores 16-Threads Box', '12-Cores Turbo 5.0GHz', 'Unlocked Edition'],
                    ],
                    [
                        'base_name'   => 'Placa-Mãe Gaming ATX Chipset Pro',
                        'desc'        => 'Placa-mãe de alta confiabilidade com slots NVMe PCIe 5.0 e dissipação reforçada.',
                        'image'       => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=500&auto=format&fit=crop&q=80',
                        'price'       => [89000, 219900],
                        'weight'      => 1200, 'dim' => [35, 30, 8],
                        'modifiers'   => ['DDR5 Wi-Fi 6E', 'DDR5 Ultra Steel', 'Black Armor Series'],
                    ],
                    [
                        'base_name'   => 'Gabinete Gamer Vidro Temperado',
                        'desc'        => 'Gabinete mid-tower com lateral em vidro temperado, mesh frontal e fluxo de ar.',
                        'image'       => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=500&auto=format&fit=crop&q=80',
                        'price'       => [34900, 79900],
                        'weight'      => 5800, 'dim' => [50, 45, 25],
                        'modifiers'   => ['Mesh Preto com 4 Fans', 'White Edition ARGB', 'Airflow Max Preto'],
                    ],
                ]
            ],
            'audio' => [
                'seller_index' => 4,
                'items' => [
                    [
                        'base_name'   => 'Headphone Bluetooth Cancelamento Ruído ANC',
                        'desc'        => 'Fone circumaural acústico com cancelamento ativo de ruído, bateria de 40h e áudio Hi-Res.',
                        'image'       => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&auto=format&fit=crop&q=80',
                        'price'       => [44900, 169900],
                        'weight'      => 450, 'dim' => [22, 18, 9],
                        'modifiers'   => ['Preto Fosco ANC', 'Cinza Lunar Hi-Res', 'Studio Edition Silver'],
                    ],
                    [
                        'base_name'   => 'Caixa de Som Portátil Bluetooth Resistente à Água',
                        'desc'        => 'Caixa acústica compacta com graves potentes, bateria para até 15h e certificação IPX7.',
                        'image'       => 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=500&auto=format&fit=crop&q=80',
                        'price'       => [19900, 69900],
                        'weight'      => 600, 'dim' => [20, 10, 10],
                        'modifiers'   => ['20W Preto Grafite', '30W Azul Marinho', 'Estéreo Dual Link'],
                    ],
                    [
                        'base_name'   => 'Microfone Condensador Estúdio Podcast',
                        'desc'        => 'Microfone condensador cardioide com suporte shockmount para captação vocal e streaming.',
                        'image'       => 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?w=500&auto=format&fit=crop&q=80',
                        'price'       => [29900, 89900],
                        'weight'      => 750, 'dim' => [25, 15, 10],
                        'modifiers'   => ['Com Braço Articulado', 'Kit Estúdio com Shockmount', 'Desktop Preto Fosco'],
                    ],
                ]
            ],
            'moda' => [
                'seller_index' => 2,
                'items' => [
                    [
                        'base_name'   => 'Tênis Running Performance Amortecimento',
                        'desc'        => 'Tênis de corrida com entressola responsiva e solado de borracha aderente.',
                        'image'       => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500&auto=format&fit=crop&q=80',
                        'price'       => [24900, 69900],
                        'weight'      => 700, 'dim' => [32, 22, 14],
                        'modifiers'   => ['Vermelho Racing Tam 41', 'Preto & Branco Tam 42', 'Cinza Chumbo Tam 40'],
                    ],
                    [
                        'base_name'   => 'Mochila Urbana Impermeável para Notebook',
                        'desc'        => 'Mochila com tecido repelente à água, bolso antifurto e compartimento acolchoado até 16".',
                        'image'       => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&auto=format&fit=crop&q=80',
                        'price'       => [12900, 32900],
                        'weight'      => 850, 'dim' => [45, 32, 15],
                        'modifiers'   => ['Oxford Preto 25L', 'Cinza Grafite 30L', 'Executiva Slim'],
                    ],
                    [
                        'base_name'   => 'Jaqueta Corta-Vento Streetwear Techwear',
                        'desc'        => 'Jaqueta com tecido corta-vento leve, capuz com regulador e bolsos selados.',
                        'image'       => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&auto=format&fit=crop&q=80',
                        'price'       => [17900, 44900],
                        'weight'      => 500, 'dim' => [35, 28, 6],
                        'modifiers'   => ['Preto All-Black Tam G', 'Chumbo Fosco Tam M', 'Refletiva Tam GG'],
                    ],
                ]
            ],
            'casa-decor' => [
                'seller_index' => 3,
                'items' => [
                    [
                        'base_name'   => 'Robô Aspirador Inteligente com Mop Passa Pano',
                        'desc'        => 'Robô aspirador inteligente com navegação giroscópica e mapeamento de piso.',
                        // IMAGEM LOCAL INFALÍVEL
                        'image'       => '/products/robo-aspirador.svg',
                        'price'       => [129900, 279900],
                        'weight'      => 3400, 'dim' => [40, 40, 14],
                        'modifiers'   => ['Laser LiDAR Branco Bivolt', 'Mop 2 em 1 Preto', 'Auto-Base Inteligente'],
                    ],
                    [
                        'base_name'   => 'Cadeira de Escritório Ergonômica Mesh',
                        'desc'        => 'Cadeira giratória com encosto respirável em mesh, apoio lombar e pistão a gás.',
                        // IMAGEM LOCAL INFALÍVEL
                        'image'       => '/products/cadeira-ergonomica.svg',
                        'price'       => [64900, 169900],
                        'weight'      => 14500, 'dim' => [65, 65, 95],
                        'modifiers'   => ['Mesh Preto Apoio Lombar', 'Cinza Ergo Pro', 'All Black com Apoio de Cabeça'],
                    ],
                    [
                        'base_name'   => 'Luminária de Mesa Articulada LED Touch',
                        'desc'        => 'Luminária articulada com níveis de intensidade de luz, porta USB e base antiderrapante.',
                        'image'       => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=500&auto=format&fit=crop&q=80',
                        'price'       => [8900, 22900],
                        'weight'      => 800, 'dim' => [35, 18, 12],
                        'modifiers'   => ['Branco Fosco 3 Cores', 'Preto Metálico Regulável', 'Minimalista Dimerizável'],
                    ],
                ]
            ],
        ];

        $totalProducts = 1500;
        $batchSize = 250;
        $productsBatch = [];
        $catKeys = array_keys($catalogTemplates);

        for ($i = 1; $i <= $totalProducts; $i++) {
            $catKey = $catKeys[array_rand($catKeys)];
            $categoryData = $catalogTemplates[$catKey];
            $itemTemplate = $categoryData['items'][array_rand($categoryData['items'])];

            $modifier = $itemTemplate['modifiers'][array_rand($itemTemplate['modifiers'])];
            $productName = "{$itemTemplate['base_name']} - {$modifier} #{$i}";

            $priceCents = rand($itemTemplate['price'][0], $itemTemplate['price'][1]);
            $stock = rand(10, 120);
            $sellerId = $sellerProfileIds[$categoryData['seller_index']];

            $productsBatch[] = [
                'id'             => (string) Str::uuid(),
                'name'           => $productName,
                'slug'           => Str::slug($productName) . '-' . Str::random(5),
                'description'    => $itemTemplate['desc'],
                'price_cents'    => $priceCents,
                'stock_quantity' => $stock,
                'image_url'      => $itemTemplate['image'],
                'category_id'    => $categoryIds[$catKey],
                'seller_id'      => $sellerId,
                'weight_grams'   => $itemTemplate['weight'],
                'length_cm'      => $itemTemplate['dim'][0],
                'width_cm'       => $itemTemplate['dim'][1],
                'height_cm'      => $itemTemplate['dim'][2],
                'created_at'     => now()->subMinutes(rand(1, 10000)),
                'updated_at'     => now(),
            ];

            if (count($productsBatch) >= $batchSize) {
                DB::table('products')->insert($productsBatch);
                $productsBatch = [];
                $this->command->info("Processados {$i} de {$totalProducts} itens...");
            }
        }

        if (! empty($productsBatch)) {
            DB::table('products')->insert($productsBatch);
        }

        $this->command->info('Base resetada! Cadeira e Robô com assets locais definitivos.');
    }
}
