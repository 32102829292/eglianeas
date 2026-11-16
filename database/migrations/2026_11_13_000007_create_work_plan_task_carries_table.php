<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_task_carries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_plan_task_id')->constrained('work_plan_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_plan_task_id', 'created_at']);
            $table->index(['from_date']);
            $table->index(['to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_task_carries');
    }
};
