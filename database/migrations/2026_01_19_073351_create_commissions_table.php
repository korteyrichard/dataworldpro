<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->decimal('base_price', 10, 2);
            $table->decimal('agent_price', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->integer('quantity');
            $table->enum('status', ['pending', 'available', 'withdrawn'])->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
            
            $table->index(['agent_id', 'status']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
