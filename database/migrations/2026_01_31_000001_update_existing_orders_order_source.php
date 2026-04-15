<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing orders that don't have order_source set
        DB::table('orders')
            ->whereNull('order_source')
            ->update(['order_source' => 'dashboard']);
            
        // Update shop orders (guest orders with agent_id) to have 'shop' source
        DB::table('orders')
            ->where('is_guest_order', true)
            ->whereNotNull('agent_id')
            ->update(['order_source' => 'shop']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we're updating data
        // based on business logic, not schema changes
    }
};