<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('document_deliveries');

        Schema::create('document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('form_type', 30);
            $table->string('delivery_method', 20);
            $table->date('date_received')->nullable();
            $table->time('time_received')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('no_file_flag')->default(false);
            $table->timestamps();

            $table->index(['client_id', 'form_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_deliveries');

        Schema::create('document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('status', 30);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('tagged_at');
            $table->foreignId('tagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'tagged_at']);
        });
    }
};
