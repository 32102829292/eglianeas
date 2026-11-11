<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const ALL_STATUSES = [
        'pending',
        'in_progress',
        'completed',
        'not_implemented',
        'overdue',
        'resolved',
        'closed',
    ];

    private const CHECK_CONSTRAINT = 'kaizen_concerns_status_check';

    public function up(): void
    {
        // Expand the status check BEFORE the Admin Concern rows are backfilled
        // so PostgreSQL never rejects the new resolved/closed values.
        $this->replaceStatusConstraint(self::ALL_STATUSES);

        Schema::table('kaizen_concerns', function (Blueprint $table) {
            $table->text('resolution_notes')->nullable()->after('notes');
            $table->timestamp('resolved_at')->nullable()->after('resolution_notes');
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('resolved_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('kaizen_concern_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kaizen_concern_id')
                ->constrained('kaizen_concerns')
                ->cascadeOnDelete();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('actions_taken');
            $table->text('current_situation');
            $table->text('remaining_issues')->nullable();
            $table->timestamps();

            $table->index(['kaizen_concern_id', 'created_at']);
        });

        Schema::table('kaizen_evidences', function (Blueprint $table) {
            $table->foreignId('progress_update_id')
                ->nullable()
                ->after('kaizen_concern_id')
                ->constrained('kaizen_concern_progress_updates')
                ->cascadeOnDelete();
        });

        // Admin Concerns used the old Kaizen implementation statuses before this
        // workflow existed. Preserve the records while moving them to the new
        // four-state vocabulary.
        DB::table('kaizen_concerns')
            ->where('type', 'admin_concern')
            ->where('status', 'completed')
            ->update(['status' => 'resolved']);

        DB::table('kaizen_concerns')
            ->where('type', 'admin_concern')
            ->where('status', 'not_implemented')
            ->update(['status' => 'closed']);

        DB::table('kaizen_concerns')
            ->where('type', 'admin_concern')
            ->where('status', 'overdue')
            ->update(['status' => 'in_progress']);
    }

    public function down(): void
    {
        // Move Admin Concerns back to values the original check constraint can
        // store before the column is narrowed again.
        DB::table('kaizen_concerns')
            ->where('type', 'admin_concern')
            ->where('status', 'resolved')
            ->update(['status' => 'completed']);

        DB::table('kaizen_concerns')
            ->where('type', 'admin_concern')
            ->where('status', 'closed')
            ->update(['status' => 'not_implemented']);

        Schema::table('kaizen_evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('progress_update_id');
        });

        Schema::dropIfExists('kaizen_concern_progress_updates');

        Schema::table('kaizen_concerns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['resolution_notes', 'resolved_at']);
        });

        $this->replaceStatusConstraint([
            'pending',
            'in_progress',
            'completed',
            'not_implemented',
            'overdue',
        ]);
    }

    /**
     * @param  list<string>  $allowed
     */
    private function replaceStatusConstraint(array $allowed): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $quoted = implode(', ', array_map(fn (string $value) => "'".$value."'", $allowed));

        DB::statement(sprintf(
            'ALTER TABLE kaizen_concerns DROP CONSTRAINT IF EXISTS %s',
            self::CHECK_CONSTRAINT
        ));

        DB::statement(sprintf(
            'ALTER TABLE kaizen_concerns ADD CONSTRAINT %s CHECK (status IN (%s))',
            self::CHECK_CONSTRAINT,
            $quoted
        ));
    }
};
