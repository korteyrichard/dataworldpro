<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('afa_orders')
            && Schema::hasColumn('afa_orders', 'email')
            && !Schema::hasColumn('afa_orders', 'ghana_card')) {
            Schema::table('afa_orders', function (Blueprint $table) {
                $table->renameColumn('email', 'ghana_card');
            });
        }
    }

    public function down(): void
    {
        // This normalization may be redundant on databases where the old
        // migration already renamed the column; don't undo that prior change.
    }
};
