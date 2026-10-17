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
        // The base orders table is created by a later migration on fresh installs.
        if (Schema::hasTable('orders')
            && Schema::hasColumn('orders', 'reference_id')
            && !Schema::hasColumn('orders', 'order_pusher_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('order_pusher_status', ['disabled', 'success', 'failed'])->default('disabled')->after('reference_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'order_pusher_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('order_pusher_status');
            });
        }
    }
};
