<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicalIssue extends Model
{
    use HasFactory;

    public const CATEGORY_DAILY_ACCOMPLISHMENT = 'daily_accomplishment';
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_BIR_FORMS = 'bir_forms';
    public const CATEGORY_CLIENT_MANAGEMENT = 'client_management';
    public const CATEGORY_PERFORMANCE = 'performance';
    public const CATEGORY_DISPLAY_UI = 'display_ui';
    public const CATEGORY_LOGIN_ACCESS = 'login_access';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_DAILY_ACCOMPLISHMENT => 'Daily Accomplishment',
        self::CATEGORY_BILLING => 'Billing',
        self::CATEGORY_BIR_FORMS => 'BIR Forms',
        self::CATEGORY_CLIENT_MANAGEMENT => 'Client Management',
        self::CATEGORY_PERFORMANCE => 'Performance',
        self::CATEGORY_DISPLAY_UI => 'Display / UI',
        self::CATEGORY_LOGIN_ACCESS => 'Login / Access',
        self::CATEGORY_OTHER => 'Other',
    ];

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_CLOSED => 'Closed',
    ];

    /**
     * Only the fields the reporter is genuinely allowed to choose are mass
     * assignable. `reference`, `user_id`, `user_role`, `status` and `updated_by`
     * are all written explicitly by the controller, so a crafted request can
     * never set them (or impersonate another reporter) through mass assignment.
     */
    protected $fillable = [
        'title',
        'category',
        'description',
        'page_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $issue): void {
            if (empty($issue->reference)) {
                $issue->reference = self::nextReference();
            }
        });
    }

    /**
     * Human-readable sequential reference, e.g. ISS-00001. The unique index on
     * the column is the ultimate guard against a race.
     */
    public static function nextReference(): string
    {
        $last = static::query()->orderByDesc('id')->value('reference');

        $number = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $number = (int) $matches[1] + 1;
        }

        return 'ISS-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TechnicalIssueAttachment::class)->orderByDesc('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TechnicalIssueNote::class)->orderBy('created_at');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'badge-danger',
            self::PRIORITY_HIGH => 'badge-warn',
            self::PRIORITY_LOW => 'badge-neutral',
            default => 'badge-info',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_RESOLVED => 'badge-success',
            self::STATUS_IN_PROGRESS => 'badge-info',
            self::STATUS_CLOSED => 'badge-neutral',
            default => 'badge-warn',
        };
    }

    /**
     * A report is visible to the people responsible for the system (admins and
     * supervisors) and to the person who raised it. Everyone else is denied, so
     * one staff member can never read another's report.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return true;
        }

        return $this->user_id !== null && (int) $this->user_id === (int) $user->id;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    /**
     * Only admins and supervisors may change a report (status, priority, notes).
     * Staff can raise and follow their own reports but never manage them.
     */
    public function canManage(User $user): bool
    {
        return $user->isAdmin() || $user->isSupervisor();
    }
}
