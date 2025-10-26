<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Alert::create([
            'title' => 'Welcome to DataWorld Pro',
            'message' => 'We are excited to announce new features and improvements to our platform. Stay tuned for more updates!',
            'is_active' => true,
        ]);

        \App\Models\Alert::create([
            'title' => 'System Maintenance',
            'message' => 'Scheduled maintenance will occur on Sunday from 2:00 AM to 4:00 AM GMT. Services may be temporarily unavailable.',
            'is_active' => false,
        ]);
    }
}
