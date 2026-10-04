<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bir_form_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });

        // Seed with existing form types from BirFormStatus::FORM_TYPES
        $formTypes = [
            ['code' => 'EFPS', 'name' => 'eFPS', 'description' => 'Electronic Filing and Payment System', 'sort_order' => 1],
            ['code' => '2551Q', 'name' => 'BIR Form 2551Q', 'description' => 'Quarterly Percentage Tax Return', 'sort_order' => 2],
            ['code' => '1701', 'name' => 'BIR Form 1701', 'description' => 'Annual Income Tax Return (Individuals)', 'sort_order' => 3],
            ['code' => '1701Q', 'name' => 'BIR Form 1701Q', 'description' => 'Quarterly Income Tax Return (Individuals)', 'sort_order' => 4],
            ['code' => '2550Q', 'name' => 'BIR Form 2550Q', 'description' => 'Quarterly VAT Return', 'sort_order' => 5],
            ['code' => '1601C', 'name' => 'BIR Form 1601C', 'description' => 'Monthly Remittance Return of Creditable Income Taxes Withheld (Compensation)', 'sort_order' => 6],
            ['code' => '1601EQ', 'name' => 'BIR Form 1601EQ', 'description' => 'Quarterly Remittance Return of Creditable Income Taxes Withheld (Expanded)', 'sort_order' => 7],
            ['code' => '0619E', 'name' => 'BIR Form 0619E', 'description' => 'Monthly Remittance of Creditable VAT Withheld', 'sort_order' => 8],
            ['code' => '2550M', 'name' => 'BIR Form 2550M', 'description' => 'Monthly VAT Declaration', 'sort_order' => 9],
            ['code' => '0619F', 'name' => 'BIR Form 0619F', 'description' => 'Monthly Remittance of Final VAT Withheld', 'sort_order' => 10],
            ['code' => '1601FQ', 'name' => 'BIR Form 1601FQ', 'description' => 'Quarterly Remittance of Final Income Taxes Withheld', 'sort_order' => 11],
            ['code' => '1702Q', 'name' => 'BIR Form 1702Q', 'description' => 'Quarterly Income Tax Return (Corporations)', 'sort_order' => 12],
            ['code' => '1702', 'name' => 'BIR Form 1702', 'description' => 'Annual Income Tax Return (Corporations)', 'sort_order' => 13],
        ];

        foreach ($formTypes as $index => $form) {
            \DB::table('bir_form_types')->insert(array_merge($form, [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bir_form_types');
    }
};