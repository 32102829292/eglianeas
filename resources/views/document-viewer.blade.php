@extends('layouts.dashboard')

@section('title', $document->original_name . ' — Egliane Accounting Services')

@section('content')
<div class="doc-viewer-wrap" id="docViewerWrap">
    <a href="{{ url()->previous() }}" class="doc-back-link">&larr; Back</a>

    <div class="doc-info-header">
        <h2>{{ $document->original_name }}</h2>
        <div class="meta">
            <span>Form: {{ $document->form_type ?? 'N/A' }}</span> &middot;
            <span>Size: {{ round($document->size / 1024, 1) }} KB</span>
        </div>
    </div>

    @php
        $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
        $imageExts = ['jpg', 'jpeg', 'png'];
    @endphp

    <div class="doc-media-frame">
        @if(in_array($ext, $imageExts))
            <img src="{{ route('documents.file', $document) }}" alt="{{ $document->original_name }}" draggable="false" id="docMedia">
        @else
            <embed src="{{ route('documents.file', $document) }}" type="application/pdf" class="doc-embed" aria-label="{{ $document->original_name }} (PDF document)" id="docMedia">
        @endif

        <div class="watermark-layer">
            @php
                $wmText = 'Viewed by ' . $viewerName . ' — ' . $viewedAt->format('Y-m-d H:i');
                $positions = [
                    ['top' => '5%', 'left' => '5%'],
                    ['top' => '5%', 'left' => '55%'],
                    ['top' => '25%', 'left' => '20%'],
                    ['top' => '25%', 'left' => '70%'],
                    ['top' => '45%', 'left' => '5%'],
                    ['top' => '45%', 'left' => '50%'],
                    ['top' => '65%', 'left' => '25%'],
                    ['top' => '65%', 'left' => '75%'],
                    ['top' => '85%', 'left' => '10%'],
                    ['top' => '85%', 'left' => '60%'],
                ];
            @endphp
            @foreach($positions as $pos)
                <span class="watermark-text" style="top:{{ $pos['top'] }}; left:{{ $pos['left'] }};">{{ $wmText }}</span>
            @endforeach
        </div>
    </div>
</div>

<div class="doc-confidential-notice">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    <span>This document is confidential. Unauthorized sharing or distribution is prohibited under Egliane's Terms of Service.</span>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.getElementById('docViewerWrap');
    var media = document.getElementById('docMedia');

    wrap.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });

    if (media) {
        media.addEventListener('dragstart', function (e) {
            e.preventDefault();
        });
    }

    wrap.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C' || e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            alert('This document is confidential and cannot be copied.');
        }
        if (e.key === 'PrintScreen') {
            e.preventDefault();
            alert('This document is confidential and cannot be copied.');
        }
    });
});
</script>
@endpush
@endsection