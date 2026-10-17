<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'bundleportal_reference_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('bundleportal_reference_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'bundleportal_reference_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['bundleportal_reference_id']);
                $table->dropColumn('bundleportal_reference_id');
            });
        }
    }
};
