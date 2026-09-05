<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ADMIN / Moderação e Diretoria
        $admin = User::firstOrCreate(
            ['email' => 'admin@nexus.com'],
            [
                'name'     => 'Diretoria de Operações & Risco',
                'role'     => 'ADMIN',
                'password' => Hash::make('nexus123'),
            ]
        );

        // 2. LOGISTICS / Operador do Fulfillment
        $logistics = User::firstOrCreate(
            ['email' => 'fulfillment@nexus.com'],
            [
                'name'     => 'Operador Galpão Hub SP',
                'role'     => 'LOGISTICS',
                'password' => Hash::make('nexus123'),
            ]
        );

        // 3. CUSTOMER / Consumidor Final
        $customer = User::firstOrCreate(
            ['email' => 'comprador@gmail.com'],
            [
                'name'     => 'Lucas Consumidor',
                'role'     => 'CUSTOMER',
                'password' => Hash::make('nexus123'),
            ]
        );

        // 4. SELLER 1 / Logitech Oficial
        $sellerUser = User::firstOrCreate(
            ['email' => 'store@logitech.com'],
            [
                'name'     => 'Logitech Official Store',
                'role'     => 'SELLER',
                'password' => Hash::make('nexus123'),
            ]
        );

        $sellerProfile = SellerProfile::firstOrCreate(
            ['user_id' => $sellerUser->id],
            [
                'store_name'         => 'Logitech Brasil Oficial',
                'legal_name'         => 'Logitech do Brasil Comercio de Acessorios LTDA',
                'document_type'      => 'CNPJ',
                'document_number'    => '00123456000199',
                'state_registration' => '123.456.789.000',
                'kyc_status'         => 'APPROVED',
                'reputation_score'   => 4.95,
                'total_sales_count'  => 1420,
                'cancellation_rate'  => 0.40,
            ]
        );

        SellerWallet::firstOrCreate(
            ['seller_profile_id' => $sellerProfile->id],
            [
                'balance_available_cents' => 12500000,
                'balance_escrow_cents'    => 3420000,
            ]
        );

        // 5. Categorias e Produtos
        $tech = Category::firstOrCreate(['slug' => 'eletronicos'], ['name' => 'Eletrônicos & Periféricos']);
        $audio = Category::firstOrCreate(['slug' => 'audio'], ['name' => 'Áudio Pro & Headsets']);

        $products = [
            [
                'slug'           => 'teclado-mecanico-rgb-wireless-pro',
                'category_id'    => $tech->id,
                'seller_id'      => $sellerProfile->id,
                'name'           => 'Teclado Mecânico RGB Wireless Pro',
                'description'    => 'Switches ópticos hot-swappable, 2.4GHz Lightspeed e Bluetooth 5.2.',
                'price_cents'    => 45900,
                'stock_quantity' => 25,
                'image_url'      => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600',
            ],
            [
                'slug'           => 'mouse-ergonomico-ultraleve-g-pro',
                'category_id'    => $tech->id,
                'seller_id'      => $sellerProfile->id,
                'name'           => 'Mouse Ergonômico Ultraleve G-Pro 60g',
                'description'    => 'Sensor Hero 2 de 32.000 DPI com latência sub-1ms.',
                'price_cents'    => 29900,
                'stock_quantity' => 40,
                'image_url'      => 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=600',
            ],
            [
                'slug'           => 'headset-studio-anc-pro',
                'category_id'    => $audio->id,
                'seller_id'      => $sellerProfile->id,
                'name'           => 'Headset Studio ANC com Cancelamento Ativo',
                'description'    => 'Drivers de 50mm em titânio e áudio espacial 7.1.',
                'price_cents'    => 78900,
                'stock_quantity' => 15,
                'image_url'      => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600',
            ],
        ];

        foreach ($products as $prod) {
            Product::updateOrCreate(['slug' => $prod['slug']], $prod);
        }
    }
}
