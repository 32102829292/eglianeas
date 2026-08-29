<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'accounts' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF])
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:4'],
            'role' => ['required', 'string', 'in:'.User::ROLE_ADMIN.','.User::ROLE_STAFF],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
            'role' => $validated['role'],
            'email_verified_at' => now(),
        ]);

        ActivityLog::record(
            auth()->user(),
            'admin.user_created',
            "Created a {$user->role} account for {$user->name} ({$user->email})."
        );

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Your account is ready',
            'body' => 'An account was created for you by '.auth()->user()->name.' on '.$user->created_at?->format('M j, Y').'. Log in and set up your PIN under Security Settings to get started.',
            'type' => 'account',
            'link' => route('security.index'),
            'reminder_count' => 1,
        ]);

        return redirect()->route('admin.users.index')->with('status', "{$user->name}'s {$user->role} account created.");
    }
}