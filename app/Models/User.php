<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF, self::ROLE_CLIENT];

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
        ];
    }

    public static function generateClientCode(): string
    {
        $last = self::where('role', self::ROLE_CLIENT)
            ->whereNotNull('client_code')
            ->orderByDesc('client_code')
            ->value('client_code');

        $next = 1;
        if ($last && preg_match('/EAS-(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return 'EAS-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
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
            self::ROLE_ADMIN, self::ROLE_STAFF => route('admin.dashboard'),
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

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    public function corViewLogs(): HasMany
    {
        return $this->hasMany(CorViewLog::class, 'viewed_by');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(Billing::class, 'client_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function birFormStatuses(): HasMany
    {
        return $this->hasMany(BirFormStatus::class, 'client_id');
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

    public static function isRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }
}
