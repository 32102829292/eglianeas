<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('facebook_url')->nullable()->after('second_email');
            $table->string('messenger_url')->nullable()->after('facebook_url');
            $table->string('website_url')->nullable()->after('messenger_url');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn(['facebook_url', 'messenger_url', 'website_url']);
        });
    }
};