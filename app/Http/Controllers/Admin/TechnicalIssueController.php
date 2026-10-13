<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\TechnicalIssue;
use App\Models\TechnicalIssueAttachment;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalIssueController extends Controller
{
    /**
     * The issue board.
     *
     * Admins and supervisors see every report; everyone else sees only the
     * reports they raised. This is the same visibility rule the model enforces
     * per record, applied at the query level so one staff member can never even
     * list another's report.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $q = trim((string) $request->get('q'));
        $status = $request->get('status');
        $priority = $request->get('priority');
        $category = $request->get('category');

        $base = TechnicalIssue::query()->visibleTo($user);

        $stats = [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', TechnicalIssue::STATUS_OPEN)->count(),
            'in_progress' => (clone $base)->where('status', TechnicalIssue::STATUS_IN_PROGRESS)->count(),
            'resolved' => (clone $base)->where('status', TechnicalIssue::STATUS_RESOLVED)->count(),
            'closed' => (clone $base)->where('status', TechnicalIssue::STATUS_CLOSED)->count(),
        ];

        $query = (clone $base)->with(['user', 'updater'])->withCount('attachments');

        if ($q !== '') {
            $query->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        if ($status !== '' && $status !== null) {
            $query->where('status', $status);
        }

        if ($priority !== '' && $priority !== null) {
            $query->where('priority', $priority);
        }

        if ($category !== '' && $category !== null) {
            $query->where('category', $category);
        }

        $issues = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.technical-issues.index', [
            'issues' => $issues,
            'stats' => $stats,
            'categories' => TechnicalIssue::CATEGORIES,
            'priorities' => TechnicalIssue::PRIORITIES,
            'statuses' => TechnicalIssue::STATUSES,
            'q' => $q,
            'activeStatus' => $status,
            'activePriority' => $priority,
            'activeCategory' => $category,
            'canManage' => $user->isAdmin() || $user->isSupervisor(),
            'hasFilters' => $q !== '' || ! empty($status) || ! empty($priority) || ! empty($category),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.technical-issues.create', [
            'categories' => TechnicalIssue::CATEGORIES,
            'priorities' => TechnicalIssue::PRIORITIES,
            // Best-effort capture of the page the reporter came from, so the
            // team knows where the problem happened.
            'pageUrl' => $this->safePageUrl($request->query('from')),
        ]);
    }

    /**
     * Any operational user may raise a report. The reporter identity and the
     * initial status are taken from the server, never the request, so a crafted
     * payload cannot attribute a report to someone else or pre-set a status.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(TechnicalIssue::CATEGORIES))],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'string', 'in:'.implode(',', array_keys(TechnicalIssue::PRIORITIES))],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'screenshots' => ['nullable', 'array', 'max:5'],
            'screenshots.*' => ['file', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $issue = new TechnicalIssue([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'page_url' => $this->safePageUrl($validated['page_url'] ?? null),
        ]);

        // Privileged fields are assigned explicitly, outside mass assignment.
        $issue->user_id = auth()->id();
        $issue->user_role = auth()->user()->role;
        $issue->priority = $validated['priority'] ?? TechnicalIssue::PRIORITY_MEDIUM;
        $issue->status = TechnicalIssue::STATUS_OPEN;
        $issue->save();

        foreach ($request->file('screenshots', []) as $file) {
            $issue->attachments()->create([
                'uploaded_by' => auth()->id(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $file->store('technical-issue-attachments', 'local'),
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        $this->notifyManagers($issue);
        $this->log($issue, 'admin.technical_issue_submitted', "Submitted technical issue {$issue->reference}.");

        return redirect()->route('admin.technical-issues.show', $issue)
            ->with('status', "Thank you. Your report {$issue->reference} has been submitted.");
    }

    public function show(TechnicalIssue $issue): View
    {
        abort_unless($issue->isVisibleTo(auth()->user()), 403);

        $issue->load(['user', 'updater', 'attachments.uploader', 'notes.author']);

        return view('admin.technical-issues.show', [
            'issue' => $issue,
            'priorities' => TechnicalIssue::PRIORITIES,
            'statuses' => TechnicalIssue::STATUSES,
            'canManage' => $issue->canManage(auth()->user()),
        ]);
    }

    /**
     * Status / priority changes. Restricted to admins and supervisors, so staff
     * can never move a report through the management workflow.
     */
    public function update(Request $request, TechnicalIssue $issue): RedirectResponse
    {
        abort_unless($issue->canManage(auth()->user()), 403, 'Only admins and supervisors can manage reports.');

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(TechnicalIssue::STATUSES))],
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys(TechnicalIssue::PRIORITIES))],
        ]);

        $previousStatus = $issue->status;

        $issue->status = $validated['status'];
        $issue->priority = $validated['priority'];
        $issue->updated_by = auth()->id();
        $issue->save();

        $this->log(
            $issue,
            'admin.technical_issue_updated',
            "Updated technical issue {$issue->reference} to {$issue->statusLabel()} / {$issue->priorityLabel()}."
        );

        if ($previousStatus !== $issue->status) {
            $this->notifyReporterOfStatus($issue);
        }

        return back()->with('status', 'Report updated.');
    }

    public function addNote(Request $request, TechnicalIssue $issue): RedirectResponse
    {
        abort_unless($issue->canManage(auth()->user()), 403, 'Only admins and supervisors can add notes.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $issue->notes()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);

        // Adding a note counts as touching the report, so the "last updated by"
        // stamp stays truthful.
        $issue->updated_by = auth()->id();
        $issue->save();

        $this->log($issue, 'admin.technical_issue_note_added', "Added an internal note to technical issue {$issue->reference}.");

        return back()->with('status', 'Note added.');
    }

    public function viewAttachment(TechnicalIssue $issue, TechnicalIssueAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($issue, $attachment);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $this->safeMimeType($attachment),
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; sandbox",
        ]);
    }

    public function downloadAttachment(TechnicalIssue $issue, TechnicalIssueAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($issue, $attachment);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $this->safeMimeType($attachment),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeAttachment(TechnicalIssue $issue, TechnicalIssueAttachment $attachment): void
    {
        abort_unless((int) $attachment->technical_issue_id === (int) $issue->id, 404);
        abort_unless($issue->isVisibleTo(auth()->user()), 403);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
    }

    /**
     * Only ever serve a content type the reporter was allowed to upload. Any
     * other value falls back to a generic binary stream so a stored file can
     * never be presented to the browser as executable content.
     */
    private function safeMimeType(TechnicalIssueAttachment $attachment): string
    {
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];

        return in_array($attachment->mime_type, $allowed, true)
            ? $attachment->mime_type
            : 'application/octet-stream';
    }

    /**
     * Accept only same-origin or relative page URLs, so the captured "where it
     * happened" value cannot be turned into an off-site link.
     */
    private function safePageUrl(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: parse_url(url('/'), PHP_URL_HOST);
        $valueHost = parse_url($value, PHP_URL_HOST);

        if ($valueHost !== null && $appHost !== null && ! hash_equals($appHost, $valueHost)) {
            return null;
        }

        return mb_substr($value, 0, 2048);
    }

    private function notifyManagers(TechnicalIssue $issue): void
    {
        $link = route('admin.technical-issues.show', $issue);
        $title = 'New Technical Issue Reported';
        $body = "{$issue->reference}: {$issue->title}";

        $managers = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])
            ->whereNull('deleted_at')
            ->where('id', '!=', auth()->id())
            ->get();

        foreach ($managers as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'title' => $title,
                'body' => $body,
                'type' => 'technical_issue',
                'group_key' => "technical-issue-submitted:{$issue->id}",
                'link' => $link,
                'reminder_count' => 1,
            ]);

            PushNotificationService::send($manager, $title, $body, $link);
        }
    }

    private function notifyReporterOfStatus(TechnicalIssue $issue): void
    {
        if (! $issue->user_id || (int) $issue->user_id === (int) auth()->id()) {
            return;
        }

        $reporter = User::find($issue->user_id);
        if (! $reporter) {
            return;
        }

        $link = route('admin.technical-issues.show', $issue);
        $title = "Your report {$issue->reference} is now {$issue->statusLabel()}";
        $body = $issue->title;

        Notification::create([
            'user_id' => $reporter->id,
            'title' => $title,
            'body' => $body,
            'type' => 'technical_issue_status',
            'group_key' => "technical-issue-status:{$issue->id}",
            'link' => $link,
            'reminder_count' => 1,
        ]);

        PushNotificationService::send($reporter, $title, $body, $link);
    }

    private function log(TechnicalIssue $issue, string $action, string $description): void
    {
        ActivityLog::record(auth()->user(), $action, $description);
    }
}
