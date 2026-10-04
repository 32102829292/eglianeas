<?php

namespace App\Providers;

use App\Services\DailyJournalService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    config('services.brevo.key')
                )
            );
        });

        /* DailyJournalService memoizes the "missing journal" count per user to
           avoid repeating the same COUNT(*) for every badge on the page. The
           memo is a static, so it must be dropped whenever the request/lifecycle
           ends — otherwise a long-lived runtime (queue worker, Octane) would
           serve counts from a previous request. */
        $this->app->terminating(function (): void {
            DailyJournalService::forgetMissingCount();
        });
    }
}
