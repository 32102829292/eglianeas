<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarterly Bookkeeping mirrors the Weekly Bookkeeping workflow, with the
 * calendar quarter as the planning unit: one plan per quarter, and every target
 * hanging off that plan the same way a week does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarterly_bookkeeping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            // Retained for parity with the weekly table, which carries a legacy
            // client column that predates the per-target planning rework.
            $table->foreignId('client_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('quarter_start');
            $table->date('quarter_end');
            $table->string('status', 20)->default('not_started');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('staff_id');
            $table->index('quarter_start');
            $table->index('status');
            $table->unique('quarter_start', 'quarterly_bookkeeping_quarter_start_unique');
        });

        Schema::create('quarterly_bookkeeping_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quarterly_bookkeeping_id')->constrained('quarterly_bookkeeping')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('task_type', 20);
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_staff_name', 120)->nullable();
            $table->date('target_date')->nullable();
            $table->string('target_status', 20)->default('pending');
            $table->string('actual_status', 20)->default('pending');
            $table->string('timing', 20)->nullable();
            $table->foreignId('performed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 120)->nullable();
            $table->string('performed_by_role', 20)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->timestamp('payment_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->timestamps();

            $table->unique(
                ['quarterly_bookkeeping_id', 'client_id', 'task_type'],
                'quarterly_bookkeeping_targets_unique'
            );
            $table->index('quarterly_bookkeeping_id');
            $table->index('client_id');
            $table->index('assigned_staff_id');
            $table->index('task_type');
            $table->index('target_status');
            $table->index('actual_status');
            $table->index('timing');
            $table->index(['actual_status', 'timing']);
            $table->index('target_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarterly_bookkeeping_targets');
        Schema::dropIfExists('quarterly_bookkeeping');
    }
};
