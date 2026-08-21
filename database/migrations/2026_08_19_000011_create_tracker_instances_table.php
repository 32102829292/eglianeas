<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('tracker_services')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('todo');
            $table->string('primary_responsible', 120)->nullable();
            $table->date('date_identified')->nullable();
            $table->date('date_started')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('service_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_instances');
    }
};
