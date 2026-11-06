<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve the original AI assessment score before any manual override, and
 * store per-criterion manual adjustment scores with reasons.
 *
 * - original_ai_score: set once when the automatic assessment first runs.
 *   Never overwritten by manual reassessment, so the score sheet can always
 *   show "Original AI score" vs "Final score".
 * - criterion_scores: JSON column storing per-criterion manual adjustments
 *   (score, max points, reason) when an admin reassesses. Null when no manual
 *   per-criterion overrides exist.
 *
 * Additive and non-destructive: no existing column, row, or constraint is
 * altered; no backfill is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->unsignedTinyInteger('original_ai_score')
                ->nullable()
                ->after('achievement_score');

            $table->json('criterion_scores')
                ->nullable()
                ->after('review_note');

            $table->index('original_ai_score');
        });
    }

    public function down(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->dropIndex(['original_ai_score']);
            $table->dropColumn(['original_ai_score', 'criterion_scores']);
        });
    }
};
