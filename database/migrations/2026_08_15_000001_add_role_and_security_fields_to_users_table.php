<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('password');
            $table->string('business_name')->nullable()->after('name');
            $table->string('pin')->nullable()->after('remember_token');
            $table->timestamp('pin_set_at')->nullable()->after('pin');
            $table->unsignedBigInteger('assigned_to')->nullable()->after('pin_set_at');
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['role', 'business_name', 'pin', 'pin_set_at', 'assigned_to']);
        });
    }
};
