<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRequiresClientToWorkPlanLegends extends Migration
{
    public function up(): void
    {
        Schema::table('work_plan_legends', function (Blueprint $table) {
            $table->boolean('requires_client')->default(false)->after('requires_details');
        });

        // Q = Pickup of documents, R = Return of documents, F = Bookkeeping
        DB::table('work_plan_legends')
            ->whereIn('code', ['F', 'Q', 'R'])
            ->update(['requires_client' => true]);
    }

    public function down(): void
    {
        Schema::table('work_plan_legends', function (Blueprint $table) {
            $table->dropColumn('requires_client');
        });
    }
}