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
        Schema::table('billings', function (Blueprint $table) {
            $table->unique(['client_id', 'quarter', 'year'], 'billings_client_period_unique');
            $table->dropIndex('billings_client_id_year_quarter_index');
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropIndex('billings_client_period_unique');
            $table->index(['client_id', 'year', 'quarter']);
        });
    }
};
