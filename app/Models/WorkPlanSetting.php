<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WorkPlanSetting extends Model
{
    use HasFactory;

    private static ?array $cache = null;

    public static function resetCache(): void
    {
        static::$cache = null;
    }

    public static function populateCache(): void
    {
        static::$cache = self::all()->pluck('value', 'key')->toArray();
    }

    public static function cachedGet(string $key, mixed $default = null): mixed
    {
        if (static::$cache === null) {
            static::$cache = self::all()->pluck('value', 'key')->toArray();
        }

        return $static::$cache[$key] ?? $default;
    }

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public const KEY_EDITING_ENABLED = 'work_plan_editing_enabled';

    public const KEY_LOCKED_AT = 'work_plan_locked_at';

    public const KEY_LOCKED_BY = 'work_plan_locked_by';

    public const KEY_LAST_UPDATED = 'work_plan_last_updated';

    public const KEY_LAST_UPDATED_BY = 'work_plan_last_updated_by';

    public const KEY_MANUAL_OVERRIDE = 'work_plan_manual_override';

    public const KEY_MANUAL_OVERRIDE_UNTIL = 'work_plan_manual_override_until';

    public const KEY_SCHEDULE_OPENS_ON = 'work_plan_schedule_opens_on';

    public const KEY_SCHEDULE_OPENS_AT = 'work_plan_schedule_opens_at';

    public const KEY_SCHEDULE_LOCKS_ON = 'work_plan_schedule_locks_on';

    public const KEY_SCHEDULE_LOCKS_AT = 'work_plan_schedule_locks_at';

    public const OVERRIDE_OPEN = 'open';

    public const OVERRIDE_LOCKED = 'locked';

    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$cache !== null) {
            return static::$cache[$key] ?? $default;
        }

        $setting = self::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function set(string $key, mixed $value, ?string $description = null): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'description' => $description,
            ]
        );
    }

    /**
     * @return array{timezone: string, opens_on: string, opens_at: string, locks_on: string, locks_at: string}
     */
    public static function schedule(): array
    {
        return [
            'timezone' => (string) config('work_plan.timezone', config('app.timezone', 'Asia/Manila')),
            'opens_on' => (string) self::get(self::KEY_SCHEDULE_OPENS_ON, config('work_plan.opens_on', 'friday')),
            'opens_at' => (string) self::get(self::KEY_SCHEDULE_OPENS_AT, config('work_plan.opens_at', '00:00')),
            'locks_on' => (string) self::get(self::KEY_SCHEDULE_LOCKS_ON, config('work_plan.locks_on', 'monday')),
            'locks_at' => (string) self::get(self::KEY_SCHEDULE_LOCKS_AT, config('work_plan.locks_at', '12:00')),
        ];
    }

    public static function currentTime(?Carbon $at = null): Carbon
    {
        $timezone = self::schedule()['timezone'];

        return $at ? $at->copy()->setTimezone($timezone) : Carbon::now($timezone);
    }

    /**
     * The automatic planning window: opens Friday 00:00 and locks Monday 12:00
     * (Asia/Manila) by default. The schedule is configurable through
     * config/work_plan.php or the corresponding work_plan_settings keys.
     */
    public static function isWithinEditingWindow(?Carbon $at = null): bool
    {
        $now = self::currentTime($at);
        [, $lock] = self::windowBounds($now);

        return $now->lt($lock);
    }

    public static function isEditingEnabled(?Carbon $at = null): bool
    {
        $now = self::currentTime($at);

        $override = self::get(self::KEY_MANUAL_OVERRIDE);
        $untilRaw = self::get(self::KEY_MANUAL_OVERRIDE_UNTIL);

        if (in_array($override, [self::OVERRIDE_OPEN, self::OVERRIDE_LOCKED], true)) {
            if (! $untilRaw) {
                return $override === self::OVERRIDE_OPEN;
            }

            $until = Carbon::parse($untilRaw, $now->getTimezone());

            if ($now->lt($until)) {
                return $override === self::OVERRIDE_OPEN;
            }

            self::clearManualOverride();
        }

        return self::isWithinEditingWindow($now);
    }

    public static function setEditingEnabled(bool $enabled, ?int $performedBy = null): void
    {
        if ($enabled) {
            self::unlock($performedBy);
        } else {
            self::lock($performedBy);
        }
    }

    public static function getLockedAt(): ?string
    {
        return self::get(self::KEY_LOCKED_AT);
    }

    public static function setLockedAt(?string $lockedAt, ?int $lockedBy = null): void
    {
        self::set(self::KEY_LOCKED_AT, $lockedAt);
        self::set(self::KEY_LOCKED_BY, $lockedBy ?? null);
    }

    public static function isLocked(?Carbon $at = null): bool
    {
        return ! self::isEditingEnabled($at);
    }

    /**
     * True only when an Admin has flipped the manual override to "locked" and
     * it has not expired. This is distinct from the automatic Monday lock: it
     * blocks Staff and Supervisors, while Admin may still edit with logging.
     */
    public static function isManuallyLocked(?Carbon $at = null): bool
    {
        $now = self::currentTime($at);
        $override = self::get(self::KEY_MANUAL_OVERRIDE);

        if ($override !== self::OVERRIDE_LOCKED) {
            return false;
        }

        $untilRaw = self::get(self::KEY_MANUAL_OVERRIDE_UNTIL);

        if (! $untilRaw) {
            return true;
        }

        return $now->lt(Carbon::parse($untilRaw, $now->getTimezone()));
    }

    public static function lock(?int $lockedBy = null): void
    {
        self::set(self::KEY_MANUAL_OVERRIDE, self::OVERRIDE_LOCKED);
        self::set(self::KEY_MANUAL_OVERRIDE_UNTIL, self::getNextStateChange()?->toDateTimeString());
        self::set(self::KEY_EDITING_ENABLED, '0');
        self::setLockedAt(now()->toDateTimeString(), $lockedBy);
        self::setLastUpdated($lockedBy);
    }

    public static function unlock(?int $unlockedBy = null): void
    {
        self::set(self::KEY_MANUAL_OVERRIDE, self::OVERRIDE_OPEN);
        self::set(self::KEY_MANUAL_OVERRIDE_UNTIL, self::getNextStateChange()?->toDateTimeString());
        self::set(self::KEY_EDITING_ENABLED, '1');
        self::setLockedAt(null, null);
        self::setLastUpdated($unlockedBy);
    }

    /**
     * Return to the automatic weekly schedule immediately.
     */
    public static function clearManualOverride(): void
    {
        self::whereIn('key', [self::KEY_MANUAL_OVERRIDE, self::KEY_MANUAL_OVERRIDE_UNTIL])->delete();
    }

    public static function getLastUpdated(): ?string
    {
        return self::get(self::KEY_LAST_UPDATED);
    }

    public static function getLastUpdatedBy(): ?int
    {
        $val = self::get(self::KEY_LAST_UPDATED_BY);

        return $val !== null ? (int) $val : null;
    }

    public static function setLastUpdated(?int $performedBy = null): void
    {
        self::set(self::KEY_LAST_UPDATED, now()->toDateTimeString());
        self::set(self::KEY_LAST_UPDATED_BY, $performedBy ?? null);
    }

    /**
     * Automatic schedule only, ignoring any manual override.
     */
    public static function shouldBeLocked(?Carbon $at = null): bool
    {
        return ! self::isWithinEditingWindow($at);
    }

    public static function getNextStateChange(?Carbon $at = null): ?Carbon
    {
        $now = self::currentTime($at);
        [$open, $lock] = self::windowBounds($now);

        if ($now->lt($lock)) {
            return $lock;
        }

        $schedule = self::schedule();
        $opensOn = self::dayNumber($schedule['opens_on']);
        $nextOpen = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays($opensOn - 1);
        $nextOpen = self::atTime($nextOpen, $schedule['opens_at']);

        while ($nextOpen->lte($now)) {
            $nextOpen->addWeek();
        }

        return $nextOpen;
    }

    /**
     * @return array{0: Carbon, 1: Carbon} The current open anchor and lock instant.
     */
    private static function windowBounds(Carbon $now): array
    {
        $schedule = self::schedule();
        $opensOn = self::dayNumber($schedule['opens_on']);
        $locksOn = self::dayNumber($schedule['locks_on']);

        $open = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays($opensOn - 1);
        $open = self::atTime($open, $schedule['opens_at']);

        if ($open->gt($now)) {
            $open->subWeek();
        }

        $lock = $open->copy()->startOfWeek(Carbon::MONDAY)->addDays($locksOn - 1);
        $lock = self::atTime($lock, $schedule['locks_at']);

        while ($lock->lte($open)) {
            $lock->addWeek();
        }

        return [$open, $lock];
    }

    private static function dayNumber(string|int $day): int
    {
        if (is_numeric($day)) {
            return max(1, min(7, (int) $day));
        }

        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        return $days[strtolower(trim($day))] ?? 5;
    }

    private static function atTime(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $date->copy()->setTime($hour, $minute, 0);
    }
}
