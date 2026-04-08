<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->onDelete('set null');
            $table->decimal('referral_amount', 10, 2);
            $table->decimal('referral_percentage', 5, 2);
            $table->enum('status', ['pending', 'available', 'withdrawn'])->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
            
            $table->index(['referrer_id', 'status']);
            $table->index(['referred_agent_id', 'status']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};