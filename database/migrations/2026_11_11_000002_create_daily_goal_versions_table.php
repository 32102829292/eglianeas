<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_goal_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_goal_id')->constrained('daily_goals')->nullOnDelete();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('change_reason', 255)->nullable();
            $table->foreignId('changed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');

            $table->index(['daily_goal_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_goal_versions');
    }
};
