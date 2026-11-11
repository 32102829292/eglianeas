<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_scoring_config', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique(); // e.g. 'quality_weight', 'timeliness_weight', 'responsiveness_weight', 'client_feedback_weight', 'timeliness_on_time_score', 'timeliness_1_day_late_score', etc.
            $table->text('config_value'); // JSON-encoded or plain text
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['config_key'], 'scoring_config_key_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_scoring_config');
    }
};
