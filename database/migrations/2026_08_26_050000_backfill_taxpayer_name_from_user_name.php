<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE client_profiles
            SET taxpayer_name = users.name
            FROM users
            WHERE users.id = client_profiles.user_id
              AND client_profiles.taxpayer_name IS NULL
              AND users.name != \'\'
        ');
    }

    public function down(): void
    {
        // Irreversible — this is a one-time data fix, not a schema change.
    }
};
