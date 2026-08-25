<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonatorId = session('impersonator_id');

        if ($impersonatorId) {
            if (! Auth::check()) {
                session()->forget('impersonator_id');
                return redirect()->route('login');
            }

            $impersonator = \App\Models\User::find($impersonatorId);

            if (! $impersonator || $impersonator->trashed()) {
                session()->forget('impersonator_id');
                Auth::logout();
                return redirect()->route('login')->with('error', 'Original admin session is no longer available.');
            }

            view()->share('impersonator', $impersonator);
            view()->share('isImpersonating', true);

            $blockedPaths = ['security', 'webauthn', 'password'];
            $currentRoute = $request->route()?->getName() ?? '';

            foreach ($blockedPaths as $blocked) {
                if (str_starts_with($currentRoute, $blocked)) {
                    abort(403, 'This action is not available while viewing as another user. Exit to Admin first.');
                }
            }
        } else {
            view()->share('isImpersonating', false);
        }

        return $next($request);
    }
}
