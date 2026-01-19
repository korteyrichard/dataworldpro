<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('afa_orders', function (Blueprint $table) {
            $table->renameColumn('email', 'ghana_card');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('afa_orders', function (Blueprint $table) {
            $table->renameColumn('ghana_card', 'email');
        });
    }
};