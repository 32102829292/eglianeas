<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KaizenConcern extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = "pending";
    public const STATUS_IN_PROGRESS = "in_progress";
    public const STATUS_COMPLETED = "completed";
    public const STATUS_OVERDUE = "overdue";

    public const STATUSES = [
        self::STATUS_PENDING => "Pending",
        self::STATUS_IN_PROGRESS => "In Progress",
        self::STATUS_COMPLETED => "Completed",
        self::STATUS_OVERDUE => "Overdue",
    ];

    protected $fillable = [
        "date_identified",
        "challenge",
        "recommended_solution",
        "target_date",
        "implementation_date",
        "assigned_staff_id",
        "status",
        "notes",
        "evidence_path",
        "evidence_name",
        "evidence_mime",
        "created_by",
    ];

    protected function casts(): array
    {
        return [
            "date_identified" => "date",
            "target_date" => "date",
            "implementation_date" => "date",
        ];
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, "assigned_staff_id");
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function checklistItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ChecklistItem::class, "checklistable");
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(KaizenEvidence::class, "kaizen_concern_id")->orderBy("created_at", "desc");
    }

    public function hasEvidence(): bool
    {
        return $this->evidences()->exists() || $this->evidence_path !== null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => "badge-success",
            self::STATUS_IN_PROGRESS => "badge-info",
            self::STATUS_OVERDUE => "badge-danger",
            default => "badge-warn",
        };
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_staff_id === $user->id;
    }

    public function isUnassigned(): bool
    {
        return $this->assigned_staff_id === null;
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isAssignedTo($user)) {
            return true;
        }

        if ($this->isUnassigned() && $user->isOperational()) {
            return true;
        }

        return false;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where("assigned_staff_id", $user->id)
              ->orWhere(function ($q2) {
                  $q2->whereNull("assigned_staff_id")
                     ->whereIn("status", [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
              });
        });
    }

    /**
     * Admin and Supervisor roles may manage the checklist structure, but only
     * for concerns they are allowed to see. Staff may only complete items.
     */
    public function canManageChecklist(User $user): bool
    {
        return ($user->isAdmin() || $user->isSupervisor()) && $this->isVisibleTo($user);
    }

    /**
     * Anyone who is allowed to work on a concern may attach evidence of their
     * own implementation. This mirrors how Priority items already authorise
     * uploads, so Staff can document the concerns they are assigned (and
     * operational Staff can document unassigned concerns) without widening
     * access to anyone else's records.
     */
    public function canUploadEvidence(User $user): bool
    {
        return $this->isVisibleTo($user);
    }
}
