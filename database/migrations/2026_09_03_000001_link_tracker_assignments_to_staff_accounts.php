<?php

use App\Models\TeamMember;
use App\Models\TrackerAssignment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('tracker_assignments', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('instance_id')->constrained('users')->nullOnDelete();
        });

        $nameToUserId = User::query()
            ->where('role', User::ROLE_STAFF)
            ->get(['id', 'name'])
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id]);

        foreach (TeamMember::whereNotNull('user_id')->get() as $member) {
            $nameToUserId[mb_strtolower(trim($member->name))] = $member->user_id;
        }

        foreach (TrackerAssignment::whereNull('staff_id')->get() as $assignment) {
            $staffId = $nameToUserId[mb_strtolower(trim((string) $assignment->staff_name))] ?? null;
            if ($staffId) {
                $assignment->staff_id = $staffId;
                $assignment->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        Schema::table('tracker_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
