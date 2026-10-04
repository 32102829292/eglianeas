<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('contact_no');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('declined_at')->nullable()->after('approved_by');
            $table->foreignId('declined_by')->nullable()->after('declined_at')
                ->constrained('users')->nullOnDelete();
            $table->string('decline_reason', 500)->nullable()->after('declined_by');
        });

        DB::table('users')
            ->where('role', User::ROLE_CLIENT)
            ->whereNull('approved_at')
            ->whereNull('declined_at')
            ->update(['approved_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('declined_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'declined_at', 'decline_reason']);
        });
    }
};