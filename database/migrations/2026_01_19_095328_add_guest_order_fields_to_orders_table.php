<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'buyer_email')) {
                $table->string('buyer_email')->nullable()->after('payment_reference');
            }
            if (!Schema::hasColumn('orders', 'is_guest_order')) {
                $table->boolean('is_guest_order')->default(false)->after('buyer_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'total_amount', 
                'payment_method',
                'payment_reference',
                'buyer_email',
                'is_guest_order'
            ]);
        });
    }
};
