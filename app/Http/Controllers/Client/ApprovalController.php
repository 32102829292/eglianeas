<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function status(): View
    {
        return view('client.pending-approval', [
            'status' => auth()->user()->approvalStatus(),
            'client' => auth()->user(),
        ]);
    }
}