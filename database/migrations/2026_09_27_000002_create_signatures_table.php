<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('policy_version', 20);
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};