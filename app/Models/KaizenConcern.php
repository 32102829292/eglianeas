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
    public const STATUS_IMPLEMENTED = "completed";
    public const STATUS_NOT_IMPLEMENTED = "not_implemented";
    public const STATUS_OVERDUE = "overdue";

    /**
     * The original name of the terminal status, kept as an alias so existing
     * callers keep working. It is the same stored value as STATUS_IMPLEMENTED
     * and is only labelled "Implemented" in the UI.
     */
    public const STATUS_COMPLETED = self::STATUS_IMPLEMENTED;

    public const STATUSES = [
        self::STATUS_PENDING => "Pending",
        self::STATUS_IN_PROGRESS => "In Progress",
        self::STATUS_IMPLEMENTED => "Implemented",
        self::STATUS_NOT_IMPLEMENTED => "Not Implemented",
        self::STATUS_OVERDUE => "Overdue",
    ];

    /**
     * Admin Concerns and Employee Suggestions share kaizen_concerns, so `type` is
     * what keeps the Improvement Suggestions board to suggestions only. It is
     * written by the endpoint that creates the record and is never chosen by the
     * person submitting it.
     */
    public const TYPE_EMPLOYEE_SUGGESTION = "employee_suggestion";

    public const TYPE_ADMIN_CONCERN = "admin_concern";

    public const TYPES = [
        self::TYPE_EMPLOYEE_SUGGESTION => "Employee Suggestion",
        self::TYPE_ADMIN_CONCERN => "Admin Concern",
    ];

    protected $fillable = [
        "type",
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

    public function isAdminConcern(): bool
    {
        return $this->type === self::TYPE_ADMIN_CONCERN;
    }

    public function isEmployeeSuggestion(): bool
    {
        return $this->type === self::TYPE_EMPLOYEE_SUGGESTION;
    }

    /**
     * Restricts a query to the records that belong on the Improvement Suggestions
     * board, so Admin Concerns are never listed as employee suggestions.
     *
     * Rows written before the `type` column existed are backfilled to
     * employee_suggestion, but the scope also treats a null type as a suggestion
     * so an older database keeps working instead of showing an empty board.
     */
    public function scopeEmployeeSuggestions(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('type', self::TYPE_EMPLOYEE_SUGGESTION)
                ->orWhereNull('type');
        });
    }

    public function scopeAdminConcerns(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ADMIN_CONCERN);
    }

    /**
     * The status actually shown to the reader, worked out from the
     * implementation state instead of trusting the stored column on its own.
     *
     * The existing implementation workflow writes `status` and
     * `implementation_date` together, so they never legitimately disagree. When
     * they do, the implementation state wins, which means a suggestion can never
     * be presented as Implemented without something behind it:
     *
     *  - a closed "Not Implemented" outcome is never second-guessed, because it
     *    is the other terminal outcome the board has to keep offering;
     *  - the confirmed status, or an implementation date on a suggestion that has
     *    not been explicitly placed into a working state (Pending), reads as
     *    Implemented. In Progress and Overdue are states the review team chose
     *    deliberately, so a date does not override them;
     *  - anything else is reported as it is stored, and an unrecognised value
     *    falls back to Pending.
     */
    public function effectiveStatus(): string
    {
        if ($this->isNotImplemented()) {
            return self::STATUS_NOT_IMPLEMENTED;
        }

        if ($this->status === self::STATUS_IMPLEMENTED) {
            return self::STATUS_IMPLEMENTED;
        }

        if ($this->implementation_date !== null
            && ! in_array($this->status, [self::STATUS_IN_PROGRESS, self::STATUS_OVERDUE], true)) {
            return self::STATUS_IMPLEMENTED;
        }

        return array_key_exists($this->status, self::STATUSES)
            ? $this->status
            : self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->effectiveStatus()] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_IMPLEMENTED => "badge-success",
            self::STATUS_IN_PROGRESS => "badge-info",
            self::STATUS_NOT_IMPLEMENTED => "badge-neutral",
            self::STATUS_OVERDUE => "badge-danger",
            default => "badge-warn",
        };
    }

    /**
     * Implemented means the work was confirmed through the existing
     * implementation workflow, which is the status flip plus the implementation
     * date it stamps.
     */
    public function isImplemented(): bool
    {
        return $this->effectiveStatus() === self::STATUS_IMPLEMENTED;
    }

    public function isNotImplemented(): bool
    {
        return $this->status === self::STATUS_NOT_IMPLEMENTED;
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
