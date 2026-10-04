<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_bookkeeping_targets');
    }
};