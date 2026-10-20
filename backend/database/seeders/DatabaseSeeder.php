<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        // 5. Catálogo demo idempotente. Nunca truncar produtos existentes: cada SKU
        // tem slug determinístico e updateOrCreate torna a execução segura/repetível.
        $categories = [
            ['slug' => 'eletronicos', 'name' => 'Eletrônicos & Periféricos'],
            ['slug' => 'audio', 'name' => 'Áudio Pro & Headsets'],
            ['slug' => 'games-consoles', 'name' => 'Games & Consoles'],
            ['slug' => 'casa-escritorio', 'name' => 'Casa & Escritório'],
            ['slug' => 'moda', 'name' => 'Moda & Acessórios'],
        ];
        $categoryIds = [];
        foreach ($categories as $category) {
            $categoryIds[$category['slug']] = Category::firstOrCreate(
                ['slug' => $category['slug']], ['name' => $category['name']]
            )->id;
        }

        $catalog = [
            ['Teclado Mecânico RGB', 'eletronicos', 45900, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600&auto=format&fit=crop&q=80'],
            ['Mouse Sem Fio Ergonômico', 'eletronicos', 29900, 'https://images.unsplash.com/photo-1527814050087-3793815479db?w=600&auto=format&fit=crop&q=80'],
            ['Monitor Gamer 27 Polegadas', 'eletronicos', 129900, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&auto=format&fit=crop&q=80'],
            ['Headset Bluetooth ANC', 'audio', 78900, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80'],
            ['Caixa de Som Portátil', 'audio', 38900, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=600&auto=format&fit=crop&q=80'],
            ['Microfone USB para Streaming', 'audio', 64900, 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?w=600&auto=format&fit=crop&q=80'],
            ['Console Portátil Retro', 'games-consoles', 89900, 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?w=600&auto=format&fit=crop&q=80'],
            ['Controle Wireless Pro', 'games-consoles', 34900, 'https://images.unsplash.com/photo-1592840496694-26d035b52b48?w=600&auto=format&fit=crop&q=80'],
            ['Cadeira Ergonômica Mesh', 'casa-escritorio', 89900, 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=600&auto=format&fit=crop&q=80'],
            ['Luminária LED de Mesa', 'casa-escritorio', 18900, 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?w=600&auto=format&fit=crop&q=80'],
            ['Mochila Urbana Impermeável', 'moda', 32900, 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=600&auto=format&fit=crop&q=80'],
            ['Tênis Casual Performance', 'moda', 49900, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80'],
        ];
        // Remove somente demos antigas sem pedidos; produtos reais e pedidos são preservados.
        Product::where('slug', 'like', 'demo-produto-%')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))->from('order_items')->whereColumn('order_items.product_id', 'products.id');
            })->delete();

        // 120 SKUs determinísticos: cada execução corrige o mesmo SKU, sem depender de "há 100".
        for ($i = 1; $i <= 120; $i++) {
            [$baseName, $category, $basePrice, $imageUrl] = $catalog[($i - 1) % count($catalog)];
            $name = $baseName . ' Modelo ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $slug = 'demo-produto-' . $i;
            Product::updateOrCreate(['slug' => $slug], [
                'seller_id' => $sellerProfile->id,
                'category_id' => $categoryIds[$category],
                'name' => $name,
                'description' => $this->descriptionFor($name, $categories[array_search($category, array_column($categories, 'slug'))]['name']),
                'price_cents' => $basePrice + (($i - 1) % 7) * 1000,
                'stock_quantity' => 10 + (($i - 1) % 91),
                'image_url' => $imageUrl,
                'weight_grams' => 250 + (($i - 1) % 20) * 50,
                'height_cm' => 5, 'width_cm' => 20, 'length_cm' => 30,
            ]);
        }

        // Repair legacy/imported rows only when their description is actually missing.
        Product::with('category')->where(function ($query) {
            $query->whereNull('description')->orWhereRaw("btrim(description) = ''");
        })->each(function (Product $product) {
            $product->update([
                'description' => $this->descriptionFor($product->name, $product->category?->name),
            ]);
        });
    }

    private function descriptionFor(string $name, ?string $category): string
    {
        $category = $category ?: 'sua categoria';
        return sprintf(
            '%s para %s, com acabamento de qualidade, uso prático no dia a dia e envio rápido pela Nexus.',
            $name,
            mb_strtolower($category)
        );
    }
}
