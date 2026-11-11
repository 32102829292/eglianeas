<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->nullOnDelete();
            $table->date('week_start_date')->index(); // Monday of the workweek
            $table->date('target_date')->index();     // Specific day this goal is for
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->text('success_criteria')->nullable(); // Measurable completion criteria
            $table->string('status', 20)->default('active'); // active, completed, cancelled
            $table->foreignId('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('previous_values')->nullable(); // Snapshot before last edit
            $table->date('effective_from')->nullable();   // When edits became effective
            $table->timestamps();

            $table->index(['user_id', 'target_date']);
            $table->index(['week_start_date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_goals');
    }
};
