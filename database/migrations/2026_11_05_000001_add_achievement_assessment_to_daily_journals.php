<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->unsignedTinyInteger('achievement_score')->nullable();
            $table->string('achievement_category', 80)->nullable();
            $table->string('achievement_level', 60)->nullable();
            $table->text('achievement_explanation')->nullable();
            $table->json('recognition_tags')->nullable();
            $table->string('assessment_source', 20)->nullable();
            $table->string('assessment_status', 20)->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->string('assessment_hash', 64)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->index('achievement_score');
            $table->index('assessment_status');
        });
    }

    public function down(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->dropIndex(['achievement_score']);
            $table->dropIndex(['assessment_status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'achievement_score',
                'achievement_category',
                'achievement_level',
                'achievement_explanation',
                'recognition_tags',
                'assessment_source',
                'assessment_status',
                'assessed_at',
                'assessment_hash',
                'reviewed_at',
                'review_note',
            ]);
        });
    }
};
