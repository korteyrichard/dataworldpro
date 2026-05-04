<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->change();
            $table->string('network')->nullable()->change();
            $table->string('mobile_money_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('phone_number')->nullable(false)->change();
            $table->string('network')->nullable(false)->change();
            $table->string('mobile_money_name')->nullable(false)->change();
        });
    }
};