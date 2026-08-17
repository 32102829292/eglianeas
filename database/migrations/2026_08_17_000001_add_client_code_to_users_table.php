<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('client_code', 15)->nullable()->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('client_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['client_code']);
            $table->dropColumn('client_code');
        });
    }
};
