<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_plan_legends', function (Blueprint $table) {
            $table->boolean('is_non_working')->default(false)->after('is_active');
            $table->boolean('requires_details')->default(false)->after('is_non_working');
            $table->boolean('is_custom')->default(false)->after('requires_details');

            $table->index(['is_non_working', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('work_plan_legends', function (Blueprint $table) {
            $table->dropIndex(['is_non_working', 'is_active']);
            $table->dropColumn(['is_non_working', 'requires_details', 'is_custom']);
        });
    }
};
