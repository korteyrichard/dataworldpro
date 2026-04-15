<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('agent_shops', 'user_shops');
    }

    public function down(): void
    {
        Schema::rename('user_shops', 'agent_shops');
    }
};