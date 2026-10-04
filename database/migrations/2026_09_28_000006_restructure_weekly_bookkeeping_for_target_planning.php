<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Repurpose weekly_bookkeeping into a weekly target plan owned by a
        // staff/supervisor (many clients per week). The old per-client
        // workflow model is superseded by weekly_bookkeeping_targets.
        Schema::table('weekly_bookkeeping', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->dropUnique('weekly_bookkeeping_client_week_unique');
            $table->dropForeign(['client_id']);
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->index('staff_id');
        });

        // The four-stage workflow is replaced by the target planner.
        Schema::dropIfExists('weekly_bookkeeping_stages');

        // Replace the placeholder targets table created earlier with the
        // proper per-client-per-task schema.
        Schema::dropIfExists('weekly_bookkeeping_targets');
        Schema::create('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_bookkeeping_id')->constrained('weekly_bookkeeping')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('task_type', 20);
            $table->date('target_date')->nullable();
            $table->string('actual_status', 20)->default('pending');
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 120)->nullable();
            $table->string('performed_by_role', 20)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['weekly_bookkeeping_id', 'client_id', 'task_type'],
                'weekly_bookkeeping_targets_unique'
            );
            $table->index('weekly_bookkeeping_id');
            $table->index('client_id');
            $table->index('task_type');
            $table->index('actual_status');
            $table->index('target_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_bookkeeping_targets');

        Schema::create('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_bookkeeping_id')->constrained('weekly_bookkeeping')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_name', 120);
            $table->json('target_tasks')->default('[]');
            $table->date('target_date')->nullable();
            $table->string('target_status', 20)->default('pending');
            $table->string('actual_status', 20)->default('pending');
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 120)->nullable();
            $table->string('performed_by_role', 20)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->timestamps();

            $table->index('weekly_bookkeeping_id');
            $table->index('client_id');
            $table->index('staff_id');
            $table->index('target_status');
            $table->index('actual_status');
        });

        Schema::create('weekly_bookkeeping_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_bookkeeping_id')->constrained('weekly_bookkeeping')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('staff_name', 120);
            $table->string('status', 20)->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 120)->nullable();
            $table->string('performed_by_role', 20)->nullable();
            $table->timestamps();

            $table->index('weekly_bookkeeping_id');
            $table->index('staff_id');
            $table->index('status');
            $table->unique(['weekly_bookkeeping_id', 'stage'], 'weekly_bookkeeping_stages_unique');
        });

        Schema::table('weekly_bookkeeping', function (Blueprint $table) {
            $table->dropIndex(['staff_id']);
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
            $table->foreign('client_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['client_id', 'week_start'], 'weekly_bookkeeping_client_week_unique');
        });
    }
};