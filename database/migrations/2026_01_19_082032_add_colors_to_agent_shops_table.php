<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_shops', function (Blueprint $table) {
            $table->string('primary_color')->default('#3B82F6'); // Blue
            $table->string('background_color')->default('#F1F5F9'); // Light gray
        });
    }

    public function down(): void
    {
        Schema::table('agent_shops', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'background_color']);
        });
    }
};