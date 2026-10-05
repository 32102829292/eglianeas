<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tells an Employee Suggestion apart from an Admin Concern.
 *
 * Both have always been stored in kaizen_concerns and both were written with the
 * same shape, so the Improvement Suggestions board could not tell them apart and
 * ended up labelling every row "Employee Suggestion". This adds the missing
 * discriminator and lets the board filter on it.
 *
 * Existing rows are backfilled to employee_suggestion: every record that shipped
 * before this column existed was tracked on the Improvement Suggestions board,
 * so treating them as suggestions preserves the current, working list instead of
 * emptying it. Nothing is deleted and no existing field changes meaning; records
 * created after this migration are typed by the endpoint that created them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaizen_concerns', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('id');
        });

        DB::table('kaizen_concerns')
            ->whereNull('type')
            ->update(['type' => 'employee_suggestion']);

        Schema::table('kaizen_concerns', function (Blueprint $table) {
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('kaizen_concerns', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
