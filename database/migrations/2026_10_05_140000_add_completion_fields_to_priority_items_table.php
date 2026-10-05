<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who completed a Priority item and when.
 *
 * These are purely additive audit columns: the status workflow itself is
 * untouched, and nothing new decides when an item is complete. They only
 * capture the moment the existing admin-only edit workflow moves an item to
 * "completed", so the detail page can show an accurate completion state
 * instead of inferring one from updated_at.
 *
 * Both are nullable so existing rows — including items that were already
 * completed before this migration — keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('priority_items', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('priority_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
        });
    }
};
