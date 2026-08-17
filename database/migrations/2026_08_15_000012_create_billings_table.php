<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('period_label', 80);
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('sales', 14, 2)->default(0);
            $table->decimal('rate_2551q', 5, 2)->default(3.00);
            $table->decimal('tax_2551q', 14, 2)->nullable();
            $table->decimal('tax_1701q', 14, 2)->nullable();
            $table->decimal('cash_in', 14, 2)->nullable();
            $table->decimal('fee_2551q', 14, 2)->nullable();
            $table->decimal('fee_1701q', 14, 2)->nullable();
            $table->decimal('fee_bookkeeping', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
