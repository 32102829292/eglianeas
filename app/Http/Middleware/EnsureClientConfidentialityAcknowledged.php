<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientConfidentialityAcknowledged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isClient()) {
            if (
                $user->confidentiality_ack_version !== EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION
                || $user->confidentiality_acknowledged_at === null
            ) {
                return redirect()->route('terms');
            }
        }

        return $next($request);
    }
}