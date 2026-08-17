<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('type');
            $table->string('period')->nullable();
            $table->date('due_date')->nullable();
            $table->date('filed_at')->nullable();
            $table->enum('status', ['pending', 'filed', 'rejected', 'needs_review'])->default('pending');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filings');
    }
};
