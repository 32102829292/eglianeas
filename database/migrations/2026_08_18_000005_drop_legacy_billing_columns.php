<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn([
                'sales',
                'rate_2551q',
                'tax_2551q',
                'tax_1701q',
                'fee_2551q',
                'fee_1701q',
                'fee_bookkeeping',
                'sales_submitted_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->decimal('sales', 14, 2)->default(0)->after('year');
            $table->decimal('rate_2551q', 5, 2)->default(3.00)->after('sales');
            $table->decimal('tax_2551q', 14, 2)->nullable()->after('rate_2551q');
            $table->decimal('tax_1701q', 14, 2)->nullable()->after('tax_2551q');
            $table->decimal('fee_2551q', 14, 2)->nullable()->after('cash_in');
            $table->decimal('fee_1701q', 14, 2)->nullable()->after('fee_2551q');
            $table->decimal('fee_bookkeeping', 14, 2)->nullable()->after('fee_1701q');
            $table->timestamp('sales_submitted_at')->nullable()->after('paid_at');
        });
    }
};
