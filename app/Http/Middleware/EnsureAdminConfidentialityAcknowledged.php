<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminConfidentialityAcknowledged
{
    const CURRENT_VERSION = '1.0';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isStaffOrAdmin()) {
            if (
                $user->confidentiality_ack_version !== self::CURRENT_VERSION
                || $user->confidentiality_acknowledged_at === null
            ) {
                if ($request->routeIs('admin.confidentiality.acknowledge')) {
                    return $next($request);
                }
                return redirect()->route('admin.confidentiality.acknowledge');
            }
        }

        return $next($request);
    }
}
