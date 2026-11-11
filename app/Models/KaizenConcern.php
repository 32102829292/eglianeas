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
    public const STATUS_RESOLVED = "resolved";
    public const STATUS_CLOSED = "closed";

    /**
     * The original name of the terminal status, kept as an alias so existing
     * callers keep working. It is the same stored value as STATUS_IMPLEMENTED
     * and is only labelled "Implemented" in the UI.
     */
    public const STATUS_COMPLETED = self::STATUS_IMPLEMENTED;

    /**
     * Employee Suggestions keep the original Kaizen lifecycle. Admin Concerns
     * use their own four-state workflow and never expose "Implemented".
     */
    public const STATUSES = [
        self::STATUS_PENDING => "Pending",
        self::STATUS_IN_PROGRESS => "In Progress",
        self::STATUS_IMPLEMENTED => "Implemented",
        self::STATUS_NOT_IMPLEMENTED => "Not Implemented",
        self::STATUS_OVERDUE => "Overdue",
    ];

    public const ADMIN_CONCERN_STATUSES = [
        self::STATUS_PENDING => "Pending",
        self::STATUS_IN_PROGRESS => "In Progress",
        self::STATUS_RESOLVED => "Resolved",
        self::STATUS_CLOSED => "Closed",
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
        "resolution_notes",
        "resolved_at",
        "resolved_by",
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
            "resolved_at" => "datetime",
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

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(KaizenConcernProgressUpdate::class, "kaizen_concern_id")
            ->orderByDesc("created_at")
            ->orderByDesc("id");
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, "resolved_by");
    }

    /**
     * The status choices offered for a given record type.
     *
     * @return array<string, string>
     */
    public static function statusesFor(?string $type): array
    {
        return $type === self::TYPE_ADMIN_CONCERN
            ? self::ADMIN_CONCERN_STATUSES
            : self::STATUSES;
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
        // Admin Concerns have their own four-state workflow. Legacy rows are
        // translated on read so an old "completed" record never appears as an
        // Implemented Kaizen item.
        if ($this->isAdminConcern()) {
            return match ((string) $this->status) {
                self::STATUS_RESOLVED, self::STATUS_IMPLEMENTED => self::STATUS_RESOLVED,
                self::STATUS_CLOSED, self::STATUS_NOT_IMPLEMENTED => self::STATUS_CLOSED,
                self::STATUS_IN_PROGRESS, self::STATUS_OVERDUE => self::STATUS_IN_PROGRESS,
                default => self::STATUS_PENDING,
            };
        }

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
        return self::statusesFor($this->type)[$this->effectiveStatus()] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->effectiveStatus()) {
            self::STATUS_IMPLEMENTED, self::STATUS_RESOLVED => "badge-success",
            self::STATUS_IN_PROGRESS => "badge-info",
            self::STATUS_NOT_IMPLEMENTED, self::STATUS_CLOSED => "badge-neutral",
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

    public function isResolved(): bool
    {
        return $this->isAdminConcern()
            && $this->effectiveStatus() === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->isAdminConcern()
            && $this->effectiveStatus() === self::STATUS_CLOSED;
    }

    /**
     * A terminal, successful outcome for either workflow: Implemented for an
     * Employee Suggestion, Resolved for an Admin Concern.
     */
    public function isCompleted(): bool
    {
        return $this->isAdminConcern() ? $this->isResolved() : $this->isImplemented();
    }

    /**
     * Progress updates are only part of the Admin Concern workflow and are
     * written by the assigned staff member (or an Admin). Closed and resolved
     * concerns stop accepting updates so the timeline is final.
     */
    public function canSubmitProgressUpdate(User $user): bool
    {
        if (! $this->isAdminConcern() || $this->isResolved() || $this->isClosed()) {
            return false;
        }

        return $user->isAdmin() || $this->isAssignedTo($user);
    }

    public function canResolve(User $user): bool
    {
        return $this->isAdminConcern()
            && $user->isAdmin()
            && ! $this->isResolved()
            && ! $this->isClosed();
    }

    public function canClose(User $user): bool
    {
        return $this->isAdminConcern()
            && $user->isAdmin()
            && ! $this->isClosed();
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_staff_id === $user->id;
    }

    public function isUnassigned(): bool
    {
        return $this->assigned_staff_id === null;
    }

    /**
     * Admin Concerns are need-to-know: outside Admin, a record is only visible
     * to the person it is assigned to. Employee Suggestions keep the original
     * operational rule so unassigned ideas stay open to the team.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Employees can always see the suggestions they submitted themselves,
        // even after the record has been assigned to somebody else.
        if ((int) $this->created_by === (int) $user->id) {
            return true;
        }

        if ($this->isAssignedTo($user)) {
            return true;
        }

        if ($this->isAdminConcern()) {
            return false;
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
              ->orWhere("created_by", $user->id)
              ->orWhere(function ($q2) {
                  $q2->whereNull("assigned_staff_id")
                     ->whereIn("status", [self::STATUS_PENDING, self::STATUS_IN_PROGRESS])
                     ->where(function ($q3) {
                         $q3->where("type", self::TYPE_EMPLOYEE_SUGGESTION)
                            ->orWhereNull("type");
                     });
              });
        });
    }

    /**
     * Admin and Supervisor roles may manage the checklist structure, but only
     * for concerns they are allowed to see. Staff may only complete items.
     */
    public function canManageChecklist(User $user): bool
    {
        // The checklist belongs to the Employee Suggestion implementation
        // workflow. Admin Concerns use progress updates instead.
        if ($this->isAdminConcern()) {
            return false;
        }

        return ($user->isAdmin() || $user->isSupervisor()) && $this->isVisibleTo($user);
    }

    public function canAssignStaff(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupervisor() && $this->isVisibleTo($user);
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
