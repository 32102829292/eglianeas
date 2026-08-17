<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ActivityLog::query()->with('user')->latest();

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('action', 'like', '%'.$request->get('q').'%')
                    ->orWhere('description', 'like', '%'.$request->get('q').'%');
            });
        }

        return view('admin.activity-logs', [
            'logs' => $query->paginate(25),
            'query' => $request->get('q'),
        ]);
    }
}
