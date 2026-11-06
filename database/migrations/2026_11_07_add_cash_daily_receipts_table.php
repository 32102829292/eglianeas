<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("cash_daily_receipts", function (Blueprint $table) {
            $table->id();
            $table->foreignId("cash_daily_position_id")
                ->constrained("cash_daily_positions")
                ->onDelete("cascade");
            $table->string("file_name");
            $table->string("file_type");
            $table->string("file_path");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("cash_daily_receipts");
    }
};