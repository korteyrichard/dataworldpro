<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VariationAttribute;

class VariationAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            [
                'name' => 'Size',
                'values' => ['1GB', '2GB', '3GB', '5GB', '10GB', '15GB', '20GB', '50GB', '100GB']
            ],
            [
                'name' => 'Duration',
                'values' => ['1 Day', '7 Days', '30 Days', '90 Days', 'Non-Expiry']
            ],
            [
                'name' => 'Type',
                'values' => ['Regular', 'Night', 'Weekend', 'Social Media', 'Video Streaming']
            ]
        ];

        foreach ($attributes as $attribute) {
            VariationAttribute::create($attribute);
        }
    }
}