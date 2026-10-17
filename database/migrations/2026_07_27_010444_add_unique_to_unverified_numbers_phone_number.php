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
        if (!Schema::hasTable('unverified_numbers')) {
            return;
        }

        // The table-creation migration already adds this unique index on fresh installs.
        if (!Schema::hasIndex('unverified_numbers', 'unverified_numbers_phone_number_unique', 'unique')) {
            $hasDuplicates = DB::table('unverified_numbers')
                ->select('phone_number')
                ->groupBy('phone_number')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasDuplicates) {
                throw new RuntimeException(
                    'Cannot add the unique phone_number index: duplicate unverified_numbers rows exist. '
                    . 'Resolve duplicates explicitly, then rerun migrations; no rows were deleted.'
                );
            }

            Schema::table('unverified_numbers', function (Blueprint $table) {
                $table->unique('phone_number');
            });
        }
    }

    public function down(): void
    {
        // The unique index is part of the original table schema and must remain in place.
    }
};
