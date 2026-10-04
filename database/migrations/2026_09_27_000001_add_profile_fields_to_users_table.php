<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image_path')->nullable()->after('confidentiality_ack_version');
            $table->string('position', 100)->nullable()->after('profile_image_path');
            $table->string('contact_no', 40)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_image_path', 'position', 'contact_no']);
        });
    }
};