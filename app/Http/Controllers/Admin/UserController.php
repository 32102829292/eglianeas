<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use App\Services\VerificationCodeSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly VerificationCodeSender $verificationCodeSender)
    {
    }

    public function index(): View
    {
        return view('admin.users.index', [
            'accounts' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_SUPERVISOR])
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'position' => $validated['position'] ?? null,
            'contact_no' => $validated['contact_no'] ?? null,
            'profile_image_path' => $this->storePhoto($request),
            'email_verified_at' => now(),
        ]);

        $this->verificationCodeSender->send($user);

        ActivityLog::record(
            auth()->user(),
            'admin.user_created',
            "Created a {$user->role} account for {$user->name} ({$user->email})."
        );

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Your account is ready',
            'body' => 'An account was created for you by '.auth()->user()->name.' on '.$user->created_at?->format('M j, Y').'. A verification code has been emailed to you — log in and use Forgot your PIN? to set up your PIN and get started.',
            'type' => 'account',
            'link' => route('security.index'),
            'reminder_count' => 1,
        ]);

        return redirect()->route('admin.users.index')->with('status', "{$user->name}'s {$user->role} account created.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.index', [
            'accounts' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_SUPERVISOR])
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
            'editing' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->rules($user->id));

        $user->fill([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'position' => $validated['position'] ?? null,
            'contact_no' => $validated['contact_no'] ?? null,
        ]);

        if ($request->hasFile('profile_image')) {
            $this->deleteOldPhoto($user);
            $user->profile_image_path = $this->storePhoto($request);
        }

        $user->save();

        ActivityLog::record(
            auth()->user(),
            'admin.user_updated',
            "Updated the {$user->role} account for {$user->name} ({$user->email})."
        );

        return redirect()->route('admin.users.index')->with('status', "{$user->name}'s account updated.");
    }

    public function photo(User $user)
    {
        abort_unless(
            $user->profile_image_path && Storage::disk('supabase')->exists($user->profile_image_path),
            404
        );

        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($user->profile_image_path, now()->addMinutes(30));

        return redirect($temporaryUrl)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, "You can't delete your own account.");

        $displayName = $user->name;
        $role = $user->role;
        $user->delete();

        ActivityLog::record(
            auth()->user(),
            'admin.user_deleted',
            "Deleted the {$role} account for {$displayName}."
        );

        return redirect()->route('admin.users.index')->with('status', "{$displayName}'s account deleted.");
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'.($ignoreId ? ','.$ignoreId : '')],
            'role' => ['required', 'string', 'in:'.User::ROLE_ADMIN.','.User::ROLE_STAFF.','.User::ROLE_SUPERVISOR],
            'position' => ['nullable', 'string', 'max:100'],
            'contact_no' => ['nullable', 'string', 'regex:/^(?:\+63|0)[\d\s\-()]{7,17}$/'],
            'profile_image' => ['nullable', 'file', 'image:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('profile_image')) {
            return null;
        }

        return $request->file('profile_image')->store('team-members', 'supabase');
    }

    private function deleteOldPhoto(User $user): void
    {
        if ($user->profile_image_path) {
            Storage::disk('supabase')->delete($user->profile_image_path);
        }
    }
}