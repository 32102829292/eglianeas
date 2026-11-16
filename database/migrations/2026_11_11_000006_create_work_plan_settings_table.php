<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plan_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        \Illuminate\Support\Facades\DB::table('work_plan_settings')->insert([
            [
                'key' => 'work_plan_editing_enabled',
                'value' => '1',
                'description' => 'Whether work plan editing is currently enabled (1) or locked (0)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'work_plan_locked_at',
                'value' => null,
                'description' => 'Timestamp when the work plan was last locked (Monday 12:00 PM Manila time)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'work_plan_locked_by',
                'value' => null,
                'description' => 'User ID who last locked the work plan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'work_plan_last_updated',
                'value' => null,
                'description' => 'Timestamp of the last work plan modification',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'work_plan_last_updated_by',
                'value' => null,
                'description' => 'User ID who last modified the work plan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plan_settings');
    }
};
