<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'users_role_index');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->index('status', 'billings_status_index');
        });

        Schema::table('cor_view_logs', function (Blueprint $table) {
            $table->index('viewed_at', 'cor_view_logs_viewed_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('cor_view_logs', function (Blueprint $table) {
            $table->dropIndex('cor_view_logs_viewed_at_index');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->dropIndex('billings_status_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
        });
    }
};
