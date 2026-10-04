<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_items', function (Blueprint $table) {
            $table->id();
            $table->string('task_lesson');
            $table->enum('type', ['priority_task', 'todo', 'lesson_learned']);
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_staff_id', 'status']);
            $table->index('status');
            $table->index('type');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_items');
    }
};