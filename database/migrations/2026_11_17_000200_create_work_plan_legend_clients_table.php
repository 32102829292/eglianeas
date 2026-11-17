<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkPlanLegendClientsTable extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_legend_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_plan_legend_id')->constrained('work_plan_legends')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['work_plan_legend_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_legend_clients');
    }
}