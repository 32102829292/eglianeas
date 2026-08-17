<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('confidentiality_acknowledged_at')->nullable()->after('email_verified_at');
            $table->string('confidentiality_ack_version', 10)->nullable()->after('confidentiality_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['confidentiality_acknowledged_at', 'confidentiality_ack_version']);
        });
    }
};
