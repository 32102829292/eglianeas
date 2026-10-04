<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('required_date');
            $table->text('problems_encountered');
            $table->text('achievements');
            $table->text('suggested_solutions');
            $table->json('evidence_paths')->nullable();
            $table->enum('status', ['missing', 'submitted', 'late'])->default('missing');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->integer('reminder_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'required_date'], 'daily_journals_user_date_unique');
            $table->index(['required_date', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_journals');
    }
};