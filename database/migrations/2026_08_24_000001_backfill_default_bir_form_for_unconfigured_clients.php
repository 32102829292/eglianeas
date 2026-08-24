<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bir_form_statuses') || ! Schema::hasTable('users')) {
            return;
        }

        $configured = DB::table('bir_form_statuses')->select('client_id')->distinct()->pluck('client_id');

        $clients = DB::table('users')
            ->where('role', 'client')
            ->when($configured->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $configured))
            ->pluck('id');

        $now = now();

        foreach ($clients as $clientId) {
            DB::table('bir_form_statuses')->updateOrInsert(
                ['client_id' => $clientId, 'form_type' => '2551Q'],
                [
                    'status' => 'not_filed',
                    'applicable' => true,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Data backfill is intentionally not reversible.
    }
};
