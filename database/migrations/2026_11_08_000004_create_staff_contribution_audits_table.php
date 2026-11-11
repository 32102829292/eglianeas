<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_contribution_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_id')->constrained('staff_contribution_records')->onDelete('cascade');
            $table->string('action'); // created/reversed
            $table->unsignedInteger('previous_points')->nullable();
            $table->unsignedInteger('new_points')->nullable();
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['contribution_id'], 'contrib_audit_contrib_idx');
            $table->index(['performed_by'], 'contrib_audit_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contribution_audits');
    }
};
