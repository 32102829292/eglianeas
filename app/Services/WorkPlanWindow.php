<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkPlanSetting;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for who may edit a weekly work plan.
 *
 * - Admin: may always edit any employee, including after the automatic Monday
 *   lock and even when the manual lock is on. Every such change is logged.
 * - Staff/Supervisor: may create/edit only their own weekly work plan while the
 *   automatic planning window is open (Friday 00:00 through Monday 11:59 by
 *   default, Asia/Manila). The manual lock blocks them.
 */
class WorkPlanWindow
{
    public const LOCK_MESSAGE = 'This weekly work plan is locked.';

    public static function canEdit(?User $user, ?Carbon $at = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (WorkPlanSetting::isManuallyLocked($at)) {
            return false;
        }

        return WorkPlanSetting::isEditingEnabled($at);
    }

    public static function canEditUser(?User $actor, int $targetUserId, ?Carbon $at = null): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ((int) $actor->id !== $targetUserId) {
            return false;
        }

        return self::canEdit($actor, $at);
    }

    /**
     * Abort with the shared locked message when the actor may not write.
     */
    public static function authorizeEdit(?User $actor, int $targetUserId, ?Carbon $at = null): void
    {
        abort_unless(self::canEditUser($actor, $targetUserId, $at), 403, self::LOCK_MESSAGE);
    }

    /**
     * Whether this write is an Admin override that must be logged (outside the
     * automatic window, during a manual lock context the admin can bypass, or on
     * another employee's plan).
     */
    public static function requiresOverrideLog(?User $actor, int $targetUserId, ?Carbon $at = null): bool
    {
        if (! $actor || ! $actor->isAdmin()) {
            return false;
        }

        return ! WorkPlanSetting::isEditingEnabled($at)
            || WorkPlanSetting::isManuallyLocked($at)
            || (int) $actor->id !== $targetUserId;
    }
}
