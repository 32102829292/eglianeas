<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientSurveyCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isClient() && $user->monthlySurveyDue()) {
            if (
                $request->routeIs('client.survey.show', 'client.survey.store')
                || $request->routeIs('logout')
            ) {
                return $next($request);
            }

            return redirect()->route('client.survey.show');
        }

        return $next($request);
    }
}
