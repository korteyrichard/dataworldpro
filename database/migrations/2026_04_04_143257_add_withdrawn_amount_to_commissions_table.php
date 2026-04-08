<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('withdrawn_amount', 10, 2)->default(0)->after('status');
        });
        
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->decimal('withdrawn_amount', 10, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn('withdrawn_amount');
        });
        
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropColumn('withdrawn_amount');
        });
    }
};