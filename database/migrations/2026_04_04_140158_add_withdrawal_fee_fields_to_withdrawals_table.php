<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->decimal('withdrawal_fee', 10, 2)->default(0)->after('amount');
            $table->decimal('net_amount', 10, 2)->default(0)->after('withdrawal_fee');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['withdrawal_fee', 'net_amount']);
        });
    }
};