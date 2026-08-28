@extends('layouts.dashboard')

@section('title', $document->original_name . ' — Egliane Accounting Services')

@section('content')
<style>
    .doc-viewer-wrap {
        position: relative;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        max-width: 900px;
    }
    .doc-viewer-wrap img,
    .doc-viewer-wrap embed,
    .doc-viewer-wrap iframe {
        width: 100%;
        display: block;
    }
    .watermark-layer {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        overflow: hidden;
        z-index: 10;
    }
    .watermark-text {
        position: absolute;
        color: rgba(0,0,0,0.12);
        font-size: 18px;
        font-weight: 600;
        white-space: nowrap;
        transform: rotate(-30deg);
        transform-origin: center;
    }
    .doc-info-header {
        margin-bottom: 16px;
    }
    .doc-info-header h2 {
        margin-bottom: 4px;
    }
    .doc-info-header .meta {
        color: #666;
        font-size: 0.9rem;
    }
    .doc-confidential-notice {
        margin-top: 20px;
        padding: 16px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 6px;
        font-size: 0.95rem;
        color: #664d03;
    }
</style>

<div class="doc-viewer-wrap" id="docViewerWrap">
    <a href="{{ url()->previous() }}" style="display:inline-block; margin-bottom:18px; color:#0d6efd; text-decoration:none;">&larr; Back</a>

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

    @if(in_array($ext, $imageExts))
        <img src="{{ route('documents.file', $document) }}" alt="{{ $document->original_name }}" draggable="false" id="docMedia">
    @else
        <embed src="{{ route('documents.file', $document) }}" type="application/pdf" style="width:100%; height:800px;" id="docMedia">
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

<div class="doc-confidential-notice">
    This document is confidential. Unauthorized sharing or distribution is prohibited under Egliane's Terms of Service.
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
