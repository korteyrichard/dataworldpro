<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_products', function (Blueprint $table) {
            // Drop the old foreign key constraint
            $table->dropForeign(['agent_shop_id']);
            
            // Add the new foreign key constraint to user_shops
            $table->foreign('agent_shop_id')->references('id')->on('user_shops')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('agent_products', function (Blueprint $table) {
            // Drop the new foreign key constraint
            $table->dropForeign(['agent_shop_id']);
            
            // Add back the old foreign key constraint to agent_shops
            $table->foreign('agent_shop_id')->references('id')->on('agent_shops')->onDelete('cascade');
        });
    }
};