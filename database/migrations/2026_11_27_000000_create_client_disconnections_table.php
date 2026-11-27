<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per disconnection. Reconnecting closes the row, so history is never lost.
        Schema::create('client_disconnections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('reason', 30)->index();
            $table->text('details')->nullable();
            $table->date('disconnected_at')->index();
            $table->unsignedBigInteger('disconnected_by')->nullable();
            $table->date('reconnected_at')->nullable()->index();
            $table->unsignedBigInteger('reconnected_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_disconnections');
    }
};