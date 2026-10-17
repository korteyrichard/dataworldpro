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
        // Some fresh installs create this table in a later migration.
        // Rename only when the legacy column is actually present.
        if (Schema::hasTable('afa_orders')
            && Schema::hasColumn('afa_orders', 'email')
            && !Schema::hasColumn('afa_orders', 'ghana_card')) {
            Schema::table('afa_orders', function (Blueprint $table) {
                $table->renameColumn('email', 'ghana_card');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('afa_orders')
            && Schema::hasColumn('afa_orders', 'ghana_card')
            && !Schema::hasColumn('afa_orders', 'email')) {
            Schema::table('afa_orders', function (Blueprint $table) {
                $table->renameColumn('ghana_card', 'email');
            });
        }
    }
};
