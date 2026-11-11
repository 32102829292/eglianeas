<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_evaluation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('staff_task_evaluations')->onDelete('cascade');
            $table->string('action'); // created/updated/adjusted/reversed
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['evaluation_id'], 'eval_audit_eval_idx');
            $table->index(['performed_by'], 'eval_audit_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_evaluation_audits');
    }
};
