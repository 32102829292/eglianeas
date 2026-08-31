<?php

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Http\Middleware\EnsureClientSurveyCompleted;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ImpersonationBanner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(ImpersonationBanner::class);
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'admin.confidentiality' => EnsureAdminConfidentialityAcknowledged::class,
            'client.survey' => EnsureClientSurveyCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
