<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Budaya & Sejarah', 'slug' => 'budaya-sejarah', 'icon' => 'museum', 'color' => '#8B4513'],
            ['name' => 'Kuliner', 'slug' => 'kuliner', 'icon' => 'restaurant', 'color' => '#FF6B35'],
            ['name' => 'Alam & Taman', 'slug' => 'alam-taman', 'icon' => 'park', 'color' => '#1A6B3C'],
            ['name' => 'Belanja & Pasar', 'slug' => 'belanja-pasar', 'icon' => 'shopping_bag', 'color' => '#9C27B0'],
            ['name' => 'Seni & Pertunjukan', 'slug' => 'seni-pertunjukan', 'icon' => 'theater_comedy', 'color' => '#E91E63'],
            ['name' => 'Religi & Ziarah', 'slug' => 'religi-ziarah', 'icon' => 'mosque', 'color' => '#FFB703'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
