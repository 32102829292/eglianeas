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
 * - Supervisor: may edit any employee at any time, bypassing the automatic
 *   window, but the manual lock still blocks them.
 * - Staff: may edit only their own row and only while the planning window is
 *   open (Friday through Monday 12:00 by default).
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

        if ($user->role === User::ROLE_SUPERVISOR) {
            return true;
        }

        return WorkPlanSetting::isEditingEnabled($at);
    }

    public static function canEditUser(?User $actor, int $targetUserId, ?Carbon $at = null): bool
    {
        if (! self::canEdit($actor, $at)) {
            return false;
        }

        if ($actor->isAdminOrSupervisor()) {
            return true;
        }

        return (int) $actor->id === $targetUserId;
    }

    /**
     * Abort with the shared locked message when the actor may not write.
     */
    public static function authorizeEdit(?User $actor, int $targetUserId, ?Carbon $at = null): void
    {
        abort_unless(self::canEditUser($actor, $targetUserId, $at), 403, self::LOCK_MESSAGE);
    }

    /**
     * Whether this write is an override that must be logged (Admin/Supervisor
     * writing outside the automatic window or on someone else's plan).
     */
    public static function requiresOverrideLog(?User $actor, int $targetUserId, ?Carbon $at = null): bool
    {
        if (! $actor || ! $actor->isAdminOrSupervisor()) {
            return false;
        }

        return ! WorkPlanSetting::isEditingEnabled($at)
            || (int) $actor->id !== $targetUserId;
    }
}
