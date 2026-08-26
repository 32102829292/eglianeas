<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('revenue_collected', 12, 2)->default(0);
            $table->unsignedInteger('new_billings')->default(0);
            $table->unsignedInteger('overdue_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_snapshots');
    }
};
