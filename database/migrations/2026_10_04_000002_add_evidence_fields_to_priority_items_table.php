<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('priority_items', function (Blueprint $table) {
            $table->string('evidence_path')->nullable()->after('notes');
            $table->string('evidence_name')->nullable()->after('evidence_path');
            $table->string('evidence_mime')->nullable()->after('evidence_name');
        });
    }

    public function down(): void
    {
        Schema::table('priority_items', function (Blueprint $table) {
            $table->dropColumn(['evidence_path', 'evidence_name', 'evidence_mime']);
        });
    }
};
