<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30); // bir_remittance, professional_fee, bookkeeping_fee
            $table->string('form_type', 20)->nullable(); // e.g. 2551Q, 1601C
            $table->string('label', 120);
            $table->unsignedTinyInteger('month')->nullable(); // 1-12 for monthly forms
            $table->decimal('amount', 14, 2)->default(0);
            $table->foreignId('fee_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('billing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_line_items');
    }
};
