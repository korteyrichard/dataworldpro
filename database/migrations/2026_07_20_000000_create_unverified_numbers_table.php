<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unverified_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->unique();
            $table->string('restriction_type', 30); // 'verified_number' or 'previous_order'
            $table->string('source', 30)->default('unknown'); // 'cart', 'checkout', 'shop', 'api'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unverified_numbers');
    }
};
