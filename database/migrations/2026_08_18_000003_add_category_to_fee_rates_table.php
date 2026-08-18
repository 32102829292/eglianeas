<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_rates', function (Blueprint $table) {
            $table->string('category', 30)->default('professional_fee')->after('label');
        });

        // Backfill: check if bookkeeping preset exists in settings
        $bookkeepingFee = DB::table('settings')->where('key', 'fee_bookkeeping')->value('value');
        if ($bookkeepingFee !== null && is_numeric($bookkeepingFee)) {
            DB::table('fee_rates')
                ->where('amount', (float) $bookkeepingFee)
                ->update(['category' => 'bookkeeping_fee']);
        }
    }

    public function down(): void
    {
        Schema::table('fee_rates', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
