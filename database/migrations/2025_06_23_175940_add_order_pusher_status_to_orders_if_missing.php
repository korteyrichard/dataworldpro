<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'order_pusher_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->enum('order_pusher_status', ['disabled', 'success', 'failed'])
                    ->default('disabled')
                    ->after('reference_id');
            });
        }
    }

    public function down(): void
    {
        // This is an ensure-if-missing migration; the column may predate it.
    }
};
