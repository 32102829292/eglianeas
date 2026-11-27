{{-- Usage: @include('admin.clients.partials.disconnect-modal', ['client' => $client]) --}}
@php
    $modalId = 'disconnectModal'.$client->getKey();
    $failed = $errors->disconnect->any() && (string) old('client_key') === (string) $client->getKey();
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.clients.disconnect', $client->getKey()) }}">
                @csrf
                <input type="hidden" name="client_key" value="{{ $client->getKey() }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}Label">Disconnect {{ $client->business_name ?: $client->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="form-hint">Records are kept. The client moves to Disconnected Clients and can be reconnected.</p>
                    <div class="form-group">
                        <label class="form-label" for="{{ $modalId }}Reason">Why is this client disconnected? <span class="text-danger">*</span></label>
                        <select class="form-control disconnect-reason" id="{{ $modalId }}Reason" name="reason" required>
                            <option value="">Select a reason</option>
                            @foreach (\App\Models\ClientDisconnection::REASONS as $value => $label)
                                <option value="{{ $value }}" @selected($failed && old('reason') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($failed)<div class="form-error">{{ $errors->disconnect->first('reason') }}</div>@endif
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="{{ $modalId }}Details">Details</label>
                        <textarea class="form-control disconnect-details" id="{{ $modalId }}Details" name="details" rows="3" maxlength="1000" placeholder="Required if Others" @required($failed && old('reason') === 'other')>{{ $failed ? old('details') : '' }}</textarea>
                        @if ($failed)<div class="form-error">{{ $errors->disconnect->first('details') }}</div>@endif
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="{{ $modalId }}Date">Date disconnected <span class="text-danger">*</span></label>
                        <input class="form-control" id="{{ $modalId }}Date" name="disconnected_at" type="date" max="{{ now()->toDateString() }}" value="{{ $failed ? old('disconnected_at') : now()->toDateString() }}" required>
                        @if ($failed)<div class="form-error">{{ $errors->disconnect->first('disconnected_at') }}</div>@endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-outline danger">Disconnect client</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('change', function (e) {
                if (!e.target.classList.contains('disconnect-reason')) return;
                e.target.form.querySelector('.disconnect-details').required = e.target.value === 'other';
            });
        </script>
    @endpush
@endonce

@if ($failed)
    @push('scripts')
        <script>
            window.addEventListener('load', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById(@json($modalId))).show();
            });
        </script>
    @endpush
@endif