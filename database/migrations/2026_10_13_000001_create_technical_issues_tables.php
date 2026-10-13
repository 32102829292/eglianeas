<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_issues', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();

            // The reporter is nulled rather than cascaded so a removed account
            // never erases the audit trail of an issue they raised. The role is
            // snapshotted so the record still reads correctly afterwards.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role', 20);

            $table->string('title', 255);
            $table->string('category', 50);
            $table->text('description');
            $table->string('page_url', 2048)->nullable();

            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('open');

            // Whoever last moved the issue (status/priority/notes). Null until a
            // manager touches it; combined with updated_at this answers "who
            // updated this, and when".
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('category');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('technical_issue_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_issue_id')->constrained('technical_issues')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('technical_issue_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_issue_id')->constrained('technical_issues')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_issue_notes');
        Schema::dropIfExists('technical_issue_attachments');
        Schema::dropIfExists('technical_issues');
    }
};
