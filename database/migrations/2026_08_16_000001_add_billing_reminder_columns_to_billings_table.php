<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('year');
            $table->timestamp('sales_submitted_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'sales_submitted_at']);
        });
    }
};
