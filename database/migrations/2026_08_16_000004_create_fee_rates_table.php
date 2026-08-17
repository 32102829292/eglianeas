<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_rates', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120)->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed the preset list from the legacy fee settings, then a few
        // common amounts, so existing billing records keep their values.
        $current = [];
        foreach (['fee_2551q', 'fee_1701q', 'fee_bookkeeping'] as $key) {
            $value = DB::table('settings')->where('key', $key)->value('value');
            if ($value !== null && is_numeric($value)) {
                $current[] = (float) $value;
            }
        }

        $presets = array_values(array_unique(array_merge($current, [320, 370, 500, 520, 800, 1500])));
        sort($presets);

        $now = now();
        $rows = [];
        foreach ($presets as $i => $amount) {
            $rows[] = [
                'label' => null,
                'amount' => $amount,
                'sort_order' => $i,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('fee_rates')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_rates');
    }
};
