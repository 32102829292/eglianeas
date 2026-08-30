<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1B1B3A">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title>@yield('title', 'Something went wrong') &mdash; Egliane Accounting Services</title>
    <style>
        :root {
            --navy: #1B1B3A;
            --navy-soft: #2A2A55;
            --sky: #5AB3F0;
            --bg-alt: #F5F8FC;
            --text-muted: #6B7280;
            --border: #E5E7EB;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, "Segoe UI", sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            color: var(--navy);
            background:
                radial-gradient(50% 60% at 90% 0%, rgba(90,179,240,.16), transparent 60%),
                radial-gradient(40% 50% at 0% 100%, rgba(90,179,240,.12), transparent 55%),
                var(--bg-alt);
        }
        .error-card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 18px 50px rgba(27,27,58,.14);
            padding: 40px 32px;
            text-align: center;
        }
        .error-brand { display: flex; justify-content: center; margin-bottom: 24px; }
        .error-brand img { height: 48px; width: auto; border-radius: 10px; }
        .error-code {
            font-family: 'Space Grotesk', 'Inter', sans-serif;
            font-weight: 700;
            font-size: 56px;
            line-height: 1;
            color: var(--sky);
            letter-spacing: -1px;
        }
        .error-title {
            font-family: 'Space Grotesk', 'Inter', sans-serif;
            font-weight: 600;
            font-size: 20px;
            color: var(--navy);
            margin: 14px 0 8px;
        }
        .error-message { font-size: 14.5px; line-height: 1.6; color: var(--text-muted); margin-bottom: 28px; }
        .error-btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 10px;
            background: var(--navy);
            color: #fff;
            text-decoration: none;
            font-family: 'Space Grotesk', 'Inter', sans-serif;
            font-weight: 600;
            font-size: 15px;
            transition: background .15s ease;
        }
        .error-btn:hover { background: var(--navy-soft); }
        .error-note { margin-top: 18px; font-size: 13px; color: var(--text-muted); }
        .error-note a { color: var(--sky-deep, #2E9BDE); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    @php
        $back = '/';
        $user = null;
        $actionLabel = 'Back to safety';
        try {
            $user = app(\Illuminate\Contracts\Auth\Factory::class)->guard('web')->user();
            if ($user !== null) {
                $actionLabel = 'Back to dashboard';
                if ($user->isStaffOrAdmin() && \Illuminate\Support\Facades\Route::has('admin.dashboard')) {
                    $back = route('admin.dashboard');
                } elseif ($user->isClient() && \Illuminate\Support\Facades\Route::has('client.dashboard')) {
                    $back = route('client.dashboard');
                }
            } else {
                $actionLabel = 'Go to login';
                $back = \Illuminate\Support\Facades\Route::has('login') ? route('login') : '/';
            }
        } catch (\Throwable $e) {
            $back = '/';
            $user = null;
        }
    @endphp

    <main class="error-card">
        <a class="error-brand" href="{{ $back }}">
            <img src="/images/logo-icon.png" alt="Egliane Accounting Services">
        </a>
        <div class="error-code">@yield('code', 'Oops')</div>
        <h1 class="error-title">@yield('title', 'Something went wrong')</h1>
        <p class="error-message">@yield('message', 'Please try again.')</p>
        <a class="error-btn" href="{{ $back }}">@yield('action', $actionLabel)</a>
        @if ($user === null)
            <p class="error-note"><a href="{{ $back }}">Log in</a></p>
        @endif
    </main>
</body>
</html>