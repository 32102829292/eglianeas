<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional free-text reference beside the weekly payment method. Bank
 * transfers can record their transaction reference, while cash, GCash and other
 * payments can use it for a short description. Existing payment rows are left
 * untouched and simply read as having no reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->string('payment_reference', 255)->nullable()->after('payment_at');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });
    }
};
