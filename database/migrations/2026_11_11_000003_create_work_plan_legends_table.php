<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_legends', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // e.g., 'A', 'B', ..., 'T', 'T1', 'T2', ... 'T8'
            $table->string('name', 255); // Task description
            $table->string('category', 50)->nullable(); // e.g., 'Bookkeeping', 'Tax', 'Client Service', etc.
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('usage_instructions')->nullable();
            $table->foreignId('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_legends');
    }
};
