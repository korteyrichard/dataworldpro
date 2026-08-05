<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicates, keeping the earliest entry per phone_number
        DB::statement('
            DELETE u1 FROM unverified_numbers u1
            INNER JOIN unverified_numbers u2
            WHERE u1.id > u2.id AND u1.phone_number = u2.phone_number
        ');

        Schema::table('unverified_numbers', function (Blueprint $table) {
            $table->unique('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('unverified_numbers', function (Blueprint $table) {
            $table->dropUnique(['phone_number']);
            $table->index('phone_number');
        });
    }
};
