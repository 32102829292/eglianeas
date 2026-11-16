<?php

namespace App\Http\Middleware;

use App\Support\WorkPlanSchema;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkPlanSchema
{
    public function handle(Request $request, Closure $next): Response
    {
        if (WorkPlanSchema::isUpToDate()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Database is out of date, run migrations.',
                'missing' => WorkPlanSchema::missing(),
            ], 503);
        }

        return response()->view('work-plan.schema-out-of-date', [
            'missing' => WorkPlanSchema::missing(),
        ], 503);
    }
}
