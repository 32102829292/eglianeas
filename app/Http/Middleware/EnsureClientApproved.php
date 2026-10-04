<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isClient() && ! $user->isAccountApproved()) {
            if (
                $request->routeIs('client.pending-approval')
                || $request->routeIs('logout')
            ) {
                return $next($request);
            }

            return redirect()->route('client.pending-approval');
        }

        return $next($request);
    }
}