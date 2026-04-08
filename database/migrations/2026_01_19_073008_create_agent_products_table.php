<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->decimal('agent_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['agent_shop_id', 'product_id', 'product_variant_id'], 'agent_product_unique');
            $table->index(['agent_shop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_products');
    }
};