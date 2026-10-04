<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaizen_concerns', function (Blueprint $table) {
            $table->id();
            $table->date('date_identified');
            $table->text('challenge');
            $table->text('recommended_solution');
            $table->date('target_date')->nullable();
            $table->date('implementation_date')->nullable();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_staff_id', 'status']);
            $table->index('status');
            $table->index('target_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaizen_concerns');
    }
};