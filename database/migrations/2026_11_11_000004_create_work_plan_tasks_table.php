<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->nullOnDelete();
            $table->date('work_date')->index(); // The specific work date
            $table->string('legend_code', 10)->nullable(); // References work_plan_legends.code
            $table->string('custom_title', 255)->nullable(); // Custom task title if not from legend
            $table->text('description')->nullable();
            $table->text('success_criteria')->nullable(); // Measurable completion criteria
            $table->string('status', 20)->default('pending'); // pending, completed, cancelled
            $table->date('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('client_name')->nullable(); // Client involved, if applicable
            $table->string('client_code', 20)->nullable();
            $table->json('client_details')->nullable(); // Additional client info
            $table->foreignId('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'work_date', 'status']);
            $table->index(['work_date', 'status']);
            $table->index(['legend_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_tasks');
    }
};
