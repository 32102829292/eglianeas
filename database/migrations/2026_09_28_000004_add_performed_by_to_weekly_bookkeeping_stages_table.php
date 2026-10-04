<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_bookkeeping_stages', function (Blueprint $table) {
            $table->foreignId('performed_by_id')->nullable()->after('staff_id')->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 120)->nullable()->after('staff_name');
            $table->string('performed_by_role', 20)->nullable()->after('performed_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_bookkeeping_stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('performed_by_id');
            $table->dropColumn(['performed_by_name', 'performed_by_role']);
        });
    }
};