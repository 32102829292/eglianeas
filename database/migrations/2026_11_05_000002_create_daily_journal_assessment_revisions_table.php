<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_journal_assessment_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_journal_id')->constrained('daily_journals')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);

            $table->unsignedTinyInteger('previous_score')->nullable();
            $table->string('previous_category', 80)->nullable();
            $table->string('previous_level', 60)->nullable();
            $table->text('previous_explanation')->nullable();
            $table->json('previous_tags')->nullable();
            $table->string('previous_source', 20)->nullable();
            $table->string('previous_status', 20)->nullable();

            $table->unsignedTinyInteger('new_score')->nullable();
            $table->string('new_category', 80)->nullable();
            $table->string('new_level', 60)->nullable();
            $table->text('new_explanation')->nullable();
            $table->json('new_tags')->nullable();
            $table->string('new_source', 20)->nullable();
            $table->string('new_status', 20)->nullable();

            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['daily_journal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_journal_assessment_revisions');
    }
};
