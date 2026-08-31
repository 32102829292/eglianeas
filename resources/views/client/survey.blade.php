@extends('layouts.dashboard')

@section('title', 'Monthly Satisfaction Survey — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Monthly satisfaction survey</h1>
        <p>Help us serve you better — this short survey takes less than a minute.</p>
    </div>

    <div class="card glass-card form-card-sm">
        <div class="card-head">
            <h2 class="card-title">How was your past month with us?</h2>
        </div>

        <form method="POST" action="{{ route('client.survey.store') }}">
            @csrf

            <div class="form-group">
                <p class="form-label">Overall, how satisfied are you with Egliane's services?</p>
                <div class="star-row" role="radiogroup" aria-label="Overall satisfaction">
                    @for ($i = 1; $i <= 5; $i++)
                        <label class="star-option">
                            <input type="radio" name="overall_rating" value="{{ $i }}" @checked((int) old('overall_rating') === $i)>
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26 6.6.64-5 4.46 1.5 6.64L12 16.9l-6 4.1 1.5-6.64-5-4.46 6.6-.64z"/></svg>
                            <span class="star-label">{{ $i }}</span>
                        </label>
                    @endfor
                </div>
                @error('overall_rating')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <p class="form-label">How would you rate the accounting services you received this month?</p>
                <div class="star-row" role="radiogroup" aria-label="Accounting services rating">
                    @for ($i = 1; $i <= 5; $i++)
                        <label class="star-option">
                            <input type="radio" name="service_rating" value="{{ $i }}" @checked((int) old('service_rating') === $i)>
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26 6.6.64-5 4.46 1.5 6.64L12 16.9l-6 4.1 1.5-6.64-5-4.46 6.6-.64z"/></svg>
                            <span class="star-label">{{ $i }}</span>
                        </label>
                    @endfor
                </div>
                @error('service_rating')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <p class="form-label">How easy is the client portal to use?</p>
                <div class="star-row" role="radiogroup" aria-label="Client portal rating">
                    @for ($i = 1; $i <= 5; $i++)
                        <label class="star-option">
                            <input type="radio" name="portal_rating" value="{{ $i }}" @checked((int) old('portal_rating') === $i)>
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.26 6.6.64-5 4.46 1.5 6.64L12 16.9l-6 4.1 1.5-6.64-5-4.46 6.6-.64z"/></svg>
                            <span class="star-label">{{ $i }}</span>
                        </label>
                    @endfor
                </div>
                @error('portal_rating')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="comments">Anything you'd like us to know? <small>(optional)</small></label>
                <textarea class="form-control" id="comments" name="comments" rows="4" placeholder="Suggestions, praises, or concerns…">{{ old('comments') }}</textarea>
                @error('comments')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit survey</button>
            </div>
        </form>
    </div>

    <style>
        .star-row { display: flex; gap: 8px; flex-wrap: wrap; }
        .star-option { position: relative; cursor: pointer; }
        .star-option input { position: absolute; opacity: 0; pointer-events: none; }
        .star-option svg { width: 30px; height: 30px; color: #c8cdd9; transition: color .15s ease; display: block; }
        .star-option:hover svg { color: #5AB3F0; }
        .star-option input:checked ~ .star-label,
        .star-option input:checked + svg { color: #f5a623; }
        .star-option:focus-within svg { outline: 2px solid #5AB3F0; outline-offset: 2px; border-radius: 4px; }
        .star-label { position: absolute; bottom: -16px; left: 50%; transform: translateX(-50%); font-size: 11px; color: #6b7280; }
    </style>
@endsection

@push('scripts')
    <script>
        (function () {
            var rows = document.querySelectorAll('.star-row');
            rows.forEach(function (row) {
                var stars = row.querySelectorAll('.star-option svg');
                stars.forEach(function (svg, index) {
                    svg.addEventListener('mouseenter', function () {
                        stars.forEach(function (s, i) { s.style.color = i <= index ? '#f5a623' : ''; });
                    });
                });
                row.addEventListener('mouseleave', function () {
                    var checked = row.querySelector('input:checked');
                    stars.forEach(function (s) { s.style.color = ''; });
                    if (checked) {
                        stars.forEach(function (s, i) {
                            if (i < parseInt(checked.value, 10)) s.style.color = '#f5a623';
                        });
                    }
                });
                row.addEventListener('change', function (e) {
                    var value = parseInt(e.target.value, 10);
                    stars.forEach(function (s, i) { s.style.color = i < value ? '#f5a623' : ''; });
                });
            });
        })();
    </script>
@endpush