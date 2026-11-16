<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_plan_tasks', function (Blueprint $table) {
            $table->date('original_work_date')->nullable()->after('work_date');
            $table->timestamp('carried_over_at')->nullable()->after('original_work_date');
            $table->foreignId('carried_over_by')->nullable()->after('carried_over_at')->constrained('users')->nullOnDelete();

            $table->string('legend_name_snapshot', 255)->nullable()->after('legend_code');
            $table->string('legend_category_snapshot', 50)->nullable()->after('legend_name_snapshot');

            $table->boolean('is_additional')->default(false)->after('success_criteria');
            $table->unsignedTinyInteger('additional_rating')->nullable()->after('is_additional');
            $table->foreignId('additional_rated_by')->nullable()->after('additional_rating')->constrained('users')->nullOnDelete();
            $table->timestamp('additional_rated_at')->nullable()->after('additional_rated_by');

            $table->index(['user_id', 'work_date', 'is_additional']);
            $table->index(['original_work_date']);
        });
    }

    public function down(): void
    {
        Schema::table('work_plan_tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'work_date', 'is_additional']);
            $table->dropIndex(['original_work_date']);
            $table->dropConstrainedForeignId('carried_over_by');
            $table->dropConstrainedForeignId('additional_rated_by');
            $table->dropColumn([
                'original_work_date',
                'carried_over_at',
                'legend_name_snapshot',
                'legend_category_snapshot',
                'is_additional',
                'additional_rating',
                'additional_rated_at',
            ]);
        });
    }
};
