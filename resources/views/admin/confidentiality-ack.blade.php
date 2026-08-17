@extends('layouts.dashboard')

@section('title', 'Confidentiality Acknowledgment — Egliane Accounting Services')

@section('content')
    <div class="conf-ack-overlay">
        <div class="conf-ack-card">
            <div class="conf-ack-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4m0 4h.01"/></svg>
            </div>
            <h1>Confidentiality Policy</h1>
            <p class="conf-ack-text">All client information, financial data, and documents accessible through this platform are strictly confidential. As an Admin, you agree not to disclose, share, screenshot, copy, or distribute any client information to any party outside Egliane Accounting Services, without prior written authorization.</p>
            <p class="conf-ack-text">This policy applies to all client data you access through this system, including but not limited to: personal information, financial records, tax documents, uploaded files, and any other information visible in the admin dashboard.</p>
            <p class="conf-ack-text">Violation of this policy may result in disciplinary action, including termination of your admin access.</p>
            <form method="POST" action="{{ route('admin.confidentiality.acknowledge.store') }}">
                @csrf
                <label class="checkbox-row conf-ack-check">
                    <input type="checkbox" name="agree" id="confAckCheck" value="1">
                    <span>I have read and agree to the confidentiality policy</span>
                </label>
                @error('agree')<div class="form-error">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary btn-block" id="confAckBtn" disabled>I Agree</button>
            </form>
            <p class="conf-ack-footer">You can re-read the full <a href="{{ route('terms') }}" target="_blank">Terms &amp; Confidentiality</a> anytime.</p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var cb = document.getElementById('confAckCheck');
    var btn = document.getElementById('confAckBtn');
    if (cb && btn) {
        cb.addEventListener('change', function () {
            btn.disabled = !cb.checked;
        });
    }
})();
</script>
@endpush
