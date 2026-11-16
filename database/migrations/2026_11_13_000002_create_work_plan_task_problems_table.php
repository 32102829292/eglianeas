<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_task_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_plan_task_id')->constrained('work_plan_tasks')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->text('proposed_solution')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_plan_task_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_task_problems');
    }
};
