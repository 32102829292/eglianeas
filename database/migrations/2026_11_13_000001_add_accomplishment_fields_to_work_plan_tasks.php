<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_plan_tasks', function (Blueprint $table) {
            $table->text('returned_reason')->nullable()->after('completion_notes');
            $table->string('evidence_path')->nullable()->after('returned_reason');
            $table->foreignId('reviewed_by')->nullable()->after('evidence_path')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('work_plan_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['returned_reason', 'evidence_path', 'reviewed_at']);
        });
    }
};
