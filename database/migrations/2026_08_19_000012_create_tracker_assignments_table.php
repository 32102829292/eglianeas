<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('tracker_instances')->cascadeOnDelete();
            $table->string('staff_name', 120);
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_assignments');
    }
};
