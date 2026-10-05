<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens kaizen_concerns.status so the existing "Not Implemented" state is
 * actually storable.
 *
 * The original column was created as an enum, and on PostgreSQL Laravel compiles
 * an enum to `varchar(255) check (...)`. That is valid in CREATE TABLE but not in
 * `ALTER COLUMN ... TYPE`, which is what `->change()` emits, so the previous
 * version of this migration died with:
 *
 *     SQLSTATE[42601]: 7 ERROR: syntax error at or near "check"
 *
 * The column is therefore relaxed to a plain string and the value check is
 * rebuilt as a real named constraint. The constraint has to be dropped and
 * re-added because the one created by the original enum is unnamed, so
 * PostgreSQL auto-named it kaizen_concerns_status_check, and changing a column's
 * type never rewrites an existing check constraint.
 *
 * Drivers other than PostgreSQL keep using the schema builder, so SQLite (tests)
 * and MySQL behave the same way as before.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ALL_STATUSES = ['pending', 'in_progress', 'completed', 'not_implemented', 'overdue'];

    /** @var list<string> */
    private const ORIGINAL_STATUSES = ['pending', 'in_progress', 'completed', 'overdue'];

    private const CHECK_CONSTRAINT = 'kaizen_concerns_status_check';

    public function up(): void
    {
        $this->replaceStatusColumn(self::ALL_STATUSES);
    }

    public function down(): void
    {
        /* A row sitting in not_implemented would violate the narrower list, so
           it is moved to a status that the original column already allowed. The
           record itself is kept. */
        DB::table('kaizen_concerns')
            ->where('status', 'not_implemented')
            ->update(['status' => 'overdue']);

        $this->replaceStatusColumn(self::ORIGINAL_STATUSES);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function replaceStatusColumn(array $allowed): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('kaizen_concerns', function (Blueprint $table) use ($allowed) {
                $table->string('status', 255)->default($allowed[0])->change();
            });

            return;
        }

        $quoted = implode(', ', array_map(fn (string $value) => "'".$value."'", $allowed));

        /* Drop the auto-named check first so the type change is not parsed with
           an inline check, then re-add the widened list explicitly. */
        DB::statement(sprintf(
            'ALTER TABLE kaizen_concerns DROP CONSTRAINT IF EXISTS %s',
            self::CHECK_CONSTRAINT
        ));

        DB::statement('ALTER TABLE kaizen_concerns ALTER COLUMN status TYPE varchar(255)');

        DB::statement(sprintf(
            'ALTER TABLE kaizen_concerns ADD CONSTRAINT %s CHECK (status IN (%s))',
            self::CHECK_CONSTRAINT,
            $quoted
        ));
    }
};
