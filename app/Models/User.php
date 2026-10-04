<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_CLIENT = 'client';

    public const ROLE_SUPERVISOR = 'supervisor';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_SUPERVISOR, self::ROLE_CLIENT];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'business_name',
        'pin',
        'pin_set_at',
        'email_verified_at',
        'confidentiality_acknowledged_at',
        'confidentiality_ack_version',
        'profile_image_path',
        'position',
        'contact_no',
        'approved_at',
        'approved_by',
        'declined_at',
        'declined_by',
        'decline_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pin',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (User $user) {
            if ($user->role === self::ROLE_CLIENT && empty($user->client_code)) {
                $user->client_code = self::generateClientCode();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'confidentiality_acknowledged_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'pin_set_at' => 'datetime',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public static function generateClientCode(): string
    {
        $codes = self::withTrashed()
            ->where('role', self::ROLE_CLIENT)
            ->whereNotNull('client_code')
            ->pluck('client_code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/EAS-(\d+)/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'EAS-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isStaffOrAdmin(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isOperational(): bool
    {
        return $this->isAdmin() || $this->isStaff() || $this->isSupervisor();
    }

    public function canManageClients(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    public function isAccountApproved(): bool
    {
        return $this->approved_at !== null && $this->declined_at === null;
    }

    public function isAccountPending(): bool
    {
        return $this->role === self::ROLE_CLIENT
            && $this->approved_at === null
            && $this->declined_at === null;
    }

    public function isAccountRejected(): bool
    {
        return $this->declined_at !== null;
    }

    public function approvalStatus(): string
    {
        if ($this->isAccountApproved()) {
            return 'approved';
        }

        return $this->isAccountRejected() ? 'rejected' : 'pending';
    }

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    public function isApprovedClient(): bool
    {
        return $this->isClient() && $this->isAccountApproved();
    }

    public function photoUrl(): ?string
    {
        if (! $this->profile_image_path) {
            return null;
        }

        return route('admin.users.photo', $this);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function hasPin(): bool
    {
        return $this->pin !== null;
    }

    public function hasWebauthnCredentials(): bool
    {
        return $this->webauthnCredentials()->exists();
    }

    public function getDashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_SUPERVISOR => route('admin.dashboard'),
            default => route('client.dashboard'),
        };
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebauthnCredential::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'client_id');
    }

    public function filings(): HasMany
    {
        return $this->hasMany(Filing::class, 'client_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'client_id');
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function getClientProfile(): ClientProfile
    {
        return $this->profile()->firstOrCreate();
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(ClientSurveyResponse::class);
    }

    public function monthlySurveyDue(): bool
    {
        if (! $this->isClient()) {
            return false;
        }

        return ! $this->surveyResponses()
            ->where('submitted_at', '>=', now()->subDays(30))
            ->exists();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function declinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Data for the dashboard notification bell in a single query.
     *
     * The bell needs both the unread badge total and the most recent rows, and
     * those were previously two separate round trips. Because every page load
     * renders the dashboard layout, that was two extra queries per request. The
     * unread total is now carried on the limited result set as a correlated
     * subquery column, so one query returns both.
     *
     * @return array{unread: int, recent: \Illuminate\Database\Eloquent\Collection<int, Notification>}
     */
    public function notificationBellData(int $limit = 8): array
    {
        $recent = $this->notifications()
            ->select($this->notifications()->getModel()->getTable().'.*')
            ->selectSub(
                $this->notifications()->unread()->selectRaw('COUNT(*)'),
                'unread_total'
            )
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'unread' => (int) ($recent->first()->unread_total ?? 0),
            'recent' => $recent,
        ];
    }

    public function corViewLogs(): HasMany
    {
        return $this->hasMany(CorViewLog::class, 'viewed_by');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class, 'client_id');
    }

    /**
     * Companies and branches owned by this client account. The account keeps
     * personal/taxpayer data; each company carries its own operating details.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(ClientCompany::class, 'client_id')->orderBy('branch_number');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function teamMember(): HasOne
    {
        return $this->hasOne(TeamMember::class, 'user_id');
    }

    public function trackerAssignments(): HasMany
    {
        return $this->hasMany(TrackerAssignment::class, 'staff_id');
    }

    public function birFormStatuses(): HasMany
    {
        return $this->hasMany(BirFormStatus::class, 'client_id');
    }

    public function birForms(): HasMany
    {
        return $this->hasMany(BirForm::class, 'client_id');
    }

    public function infoEntries(): HasMany
    {
        return $this->hasMany(ClientInfoEntry::class)->orderBy('sort_order');
    }

    public function documentDeliveries(): HasMany
    {
        return $this->hasMany(DocumentDelivery::class, 'client_id');
    }

    public function otherServices(): HasMany
    {
        return $this->hasMany(OtherService::class, 'client_id');
    }

    public function trackerInstances(): HasMany
    {
        return $this->hasMany(TrackerInstance::class, 'client_id');
    }

    public function clientConcerns(): HasMany
    {
        return $this->hasMany(ClientConcern::class, 'client_id');
    }

    public function weeklyBookkeepingPlans(): HasMany
    {
        return $this->hasMany(WeeklyBookkeeping::class, 'staff_id');
    }

    public function dailyJournals(): HasMany
    {
        return $this->hasMany(DailyJournal::class);
    }

    public static function isRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }
}
