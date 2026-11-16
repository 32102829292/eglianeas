<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_plan_task_id')->constrained('work_plan_tasks')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->nullOnDelete();
            $table->string('action', 50); // created, updated, completed, cancelled, deleted, emergency_override
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamp('changed_at');

            $table->index(['work_plan_task_id', 'changed_at']);
            $table->index(['user_id', 'changed_at']);
            $table->index(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_audits');
    }
};
