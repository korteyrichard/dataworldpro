<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('afa_orders') && Schema::hasColumn('afa_orders', 'status')) {
            Schema::table('afa_orders', function (Blueprint $table) {
                $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'CANCELLED'])
                    ->default('PENDING')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('afa_orders') && Schema::hasColumn('afa_orders', 'status')) {
            DB::table('afa_orders')->where('status', 'PROCESSING')->update(['status' => 'PENDING']);

            Schema::table('afa_orders', function (Blueprint $table) {
                $table->enum('status', ['PENDING', 'COMPLETED', 'CANCELLED'])
                    ->default('PENDING')
                    ->change();
            });
        }
    }
};
