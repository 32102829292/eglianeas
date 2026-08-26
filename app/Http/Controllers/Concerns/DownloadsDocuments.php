<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait DownloadsDocuments
{
    protected function streamDocument(Document $document): StreamedResponse
    {
        if (! Storage::disk('supabase')->exists($document->path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('supabase')->download($document->path, $document->original_name);
    }
}
