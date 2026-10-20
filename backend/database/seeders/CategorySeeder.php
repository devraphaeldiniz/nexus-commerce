<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Eletrônicos & Hardware', 'slug' => 'eletronicos-hardware'],
            ['name' => 'Monitores & Periféricos', 'slug' => 'monitores-perifericos'],
            ['name' => 'Games & Consoles', 'slug' => 'games-consoles'],
            ['name' => 'Geral', 'slug' => 'geral'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }

        $this->command->info('CATEGORIAS_PADRAO_CRIADAS');
    }
}
