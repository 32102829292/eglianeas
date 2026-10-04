<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->timestamps();

            $table->index('weekly_bookkeeping_id');
            $table->index('staff_id');
            $table->index('status');
            $table->unique(['weekly_bookkeeping_id', 'stage'], 'weekly_bookkeeping_stages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_bookkeeping_stages');
    }
};