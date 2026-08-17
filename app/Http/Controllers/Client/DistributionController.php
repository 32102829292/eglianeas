<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\BirFormStatus;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DistributionController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $birStatuses = $user->birFormStatuses()
            ->get()
            ->pluck('status', 'form_type')
            ->toArray();

        $softcopies = $user->documents()
            ->whereNotNull('form_type')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('form_type');

        return view('client.documents.index', [
            'birStatuses' => $birStatuses,
            'softcopies' => $softcopies,
            'formTypes' => BirFormStatus::FORM_TYPES,
            'statuses' => BirFormStatus::STATUSES,
        ]);
    }

    public function download(Document $document)
    {
        abort_unless($document->client_id === auth()->id(), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }
}
