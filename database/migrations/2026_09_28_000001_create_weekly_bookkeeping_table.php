<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_bookkeeping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->string('status', 20)->default('not_started');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'week_start']);
            $table->index('week_start');
            $table->index('status');
            $table->unique(['client_id', 'week_start'], 'weekly_bookkeeping_client_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_bookkeeping');
    }
};