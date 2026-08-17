<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('business_type')->nullable();
            $table->string('line_of_business')->nullable();
            $table->string('bir_registration_type')->nullable();
            $table->string('business_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_no')->nullable();
            $table->string('second_contact_no')->nullable();
            $table->string('second_email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('mother_maiden_name')->nullable();
            $table->string('father_name')->nullable();
            $table->enum('status', ['current', 'pending', 'delinquent', 'critical'])->default('current');
            $table->enum('payment_status', ['paid', 'partial', 'unpaid'])->nullable();
            $table->date('date_started')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
