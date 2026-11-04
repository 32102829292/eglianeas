<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('cor_document_path')->nullable()->after('valid_id_document_path');
            $table->string('cor_document_name')->nullable()->after('cor_document_path');
            $table->string('cor_document_mime')->nullable()->after('cor_document_name');
            $table->timestamp('cor_document_uploaded_at')->nullable()->after('cor_document_mime');
        });
    }

    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'cor_document_path',
                'cor_document_name',
                'cor_document_mime',
                'cor_document_uploaded_at',
            ]);
        });
    }
};
