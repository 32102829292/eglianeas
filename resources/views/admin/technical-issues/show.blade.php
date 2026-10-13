@extends('layouts.dashboard')

@section('title', "{$issue->reference} — Technical Issue — Egliane Accounting Services")

@section('content')
    <div class="page-head page-head-row">
        <div>
            <h1>{{ $issue->reference }} <span class="badge {{ $issue->statusBadgeClass() }}">{{ $issue->statusLabel() }}</span></h1>
            <p>{{ $issue->title }}</p>
        </div>
        <a href="{{ route('admin.technical-issues.index') }}" class="btn btn-outline">Back to Reports</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="form-grid two" style="align-items:start;">
        {{-- ============ Report details ============ --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">Report Details</h2>
            </div>

            <div class="d-flex gap-2 flex-wrap" style="margin-bottom: 14px;">
                <span class="badge {{ $issue->priorityBadgeClass() }}">{{ $issue->priorityLabel() }} priority</span>
                <span class="badge badge-navy">{{ $issue->categoryLabel() }}</span>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <div class="issue-description" style="white-space: pre-line;">{{ $issue->description }}</div>
            </div>

            <div class="issue-facts">
                <div class="issue-fact">
                    <span class="cv-label">Reported by</span>
                    <span class="cv-value">{{ $issue->user->name ?? '—' }}</span>
                </div>
                <div class="issue-fact">
                    <span class="cv-label">Role</span>
                    <span class="cv-value">{{ ucfirst($issue->user_role) }}</span>
                </div>
                <div class="issue-fact">
                    <span class="cv-label">Reported on</span>
                    <span class="cv-value">{{ $issue->created_at?->format('F j, Y g:ia') }}</span>
                </div>
                @if ($issue->page_url)
                    <div class="issue-fact">
                        <span class="cv-label">Where it happened</span>
                        <span class="cv-value"><a href="{{ $issue->page_url }}" target="_blank" rel="noopener noreferrer">{{ $issue->page_url }}</a></span>
                    </div>
                @endif
                <div class="issue-fact">
                    <span class="cv-label">Last updated</span>
                    <span class="cv-value">
                        @if ($issue->updated_by)
                            {{ $issue->updater->name ?? '?' }} &middot; {{ $issue->updated_at?->format('F j, Y g:ia') }}
                        @else
                            Not yet updated
                        @endif
                    </span>
                </div>
            </div>

            {{-- ============ Attachments ============ --}}
            <div class="card-head" style="border-top:1px solid var(--border); padding-top:14px; margin-top:18px;">
                <h2 class="card-title">Screenshots</h2>
            </div>

            @if ($issue->attachments->isEmpty())
                <p class="muted mb-0">No screenshots attached.</p>
            @else
                <div class="kaizen-evidence-list">
                    @foreach ($issue->attachments as $attachment)
                        <div class="kaizen-evidence-item">
                            @if ($attachment->isImage())
                                <img src="{{ URL::temporarySignedRoute('admin.technical-issues.attachments.view', now()->addMinutes(30), ['issue' => $issue->id, 'attachment' => $attachment->id]) }}"
                                     alt="{{ $attachment->original_name }}" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                            @else
                                <span class="kaizen-evidence-icon" aria-hidden="true">&#128444;</span>
                            @endif
                            <div class="kaizen-evidence-meta">
                                <div class="kaizen-evidence-name">{{ $attachment->original_name }}</div>
                                <div class="kaizen-evidence-sub">
                                    Added by {{ $attachment->uploader->name ?? '?' }}
                                    &middot; {{ $attachment->created_at?->format('M j, Y') }}
                                    @if ($attachment->size)
                                        &middot; {{ number_format($attachment->size / 1024, 0) }} KB
                                    @endif
                                </div>
                            </div>
                            <div class="kaizen-evidence-actions">
                                <a href="{{ URL::temporarySignedRoute('admin.technical-issues.attachments.view', now()->addMinutes(30), ['issue' => $issue->id, 'attachment' => $attachment->id]) }}" class="btn btn-outline btn-sm" target="_blank" rel="noopener">View</a>
                                <a href="{{ URL::temporarySignedRoute('admin.technical-issues.attachments.download', now()->addMinutes(30), ['issue' => $issue->id, 'attachment' => $attachment->id]) }}" class="btn btn-outline btn-sm">Download</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============ Manager panel / Notes ============ --}}
        <div>
            @if ($canManage)
                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Manage Report</h2>
                    </div>
                    <form method="POST" action="{{ route('admin.technical-issues.update', $issue) }}">
                        @csrf
                        @method('PATCH')
                        <div class="form-grid two">
                            <div class="form-group">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($issue->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="priority">Priority</label>
                                <select class="form-control" id="priority" name="priority" required>
                                    @foreach ($priorities as $value => $label)
                                        <option value="{{ $value }}" @selected($issue->priority === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('priority')<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>

                    <form method="POST" action="{{ route('admin.technical-issues.notes.store', $issue) }}" style="margin-top:18px; border-top:1px solid var(--border); padding-top:16px;">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="note_body">Add Internal Note</label>
                            <textarea class="form-control" id="note_body" name="body" rows="3" maxlength="5000" required placeholder="Progress updates, findings, next steps...">{{ old('body') }}</textarea>
                            @error('body')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-outline">Add Note</button>
                    </form>
                </div>
            @endif

            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Notes &amp; Updates</h2>
                </div>

                @if ($issue->notes->isEmpty())
                    <p class="muted mb-0">No notes yet. {{ $canManage ? 'Add the first update above.' : '' }}</p>
                @else
                    <div class="issue-notes">
                        @foreach ($issue->notes as $note)
                            <div class="issue-note">
                                <div class="kaizen-evidence-name">{{ $note->author->name ?? '—' }}</div>
                                <div class="kaizen-evidence-sub">{{ $note->created_at?->format('F j, Y g:ia') }}</div>
                                <p style="white-space: pre-line; margin-top:6px;">{{ $note->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection