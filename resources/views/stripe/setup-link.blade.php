@php
    $content = match ($state) {
        'complete' => [
            'icon' => 'success',
            'title' => "You're all sorted",
            'message' => 'Your Stripe account is connected. Open the '.config('app.name').' app — it will show you as connected next time it refreshes. You can close this window.',
        ],
        'incomplete' => [
            'icon' => 'pending',
            'title' => 'Almost there',
            'message' => "Stripe still needs a few more details before you can be paid. Pick up where you left off below.",
        ],
        'expired' => [
            'icon' => 'warning',
            'title' => 'This link has expired',
            'message' => 'For your security, Stripe setup links expire. Please ask us to send you a new one.',
        ],
        default => [
            'icon' => 'warning',
            'title' => 'Something went wrong',
            'message' => "We couldn't open Stripe just now. Please try the link again in a few minutes, or ask us to send you a new one.",
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $content['title'] }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
        }
        .card {
            width: 100%;
            max-width: 26rem;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .brand-bar { height: 5px; background: #DC2626; }
        .card-body { padding: 32px 32px 36px; }
        .logo { width: 84px; height: 84px; display: block; margin: 0 auto 24px; }
        .status {
            width: 48px;
            height: 48px;
            margin: 0 auto 16px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status svg { width: 26px; height: 26px; }
        .status-success { background: #dcfce7; color: #16a34a; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-warning { background: #fee2e2; color: #DC2626; }
        h1 { font-size: 1.4rem; margin: 0 0 .75rem; }
        p { color: #55606e; line-height: 1.55; margin: 0; }
        a.button {
            display: inline-block;
            margin-top: 24px;
            background: #DC2626;
            color: #ffffff;
            text-decoration: none;
            padding: .8rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
        }
        a.button:hover { background: #b91c1c; }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .footer svg { width: 13px; height: 13px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand-bar"></div>
        <div class="card-body">
            <img class="logo" src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}">

            <div class="status status-{{ $content['icon'] }}" aria-hidden="true">
                @if ($content['icon'] === 'success')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                @elseif ($content['icon'] === 'pending')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
                @endif
            </div>

            <h1>{{ $content['title'] }}</h1>
            <p>{{ $content['message'] }}</p>

            @if ($continueUrl)
                <a class="button" href="{{ $continueUrl }}">Continue Stripe setup</a>
            @endif
        </div>
    </main>

    <div class="footer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        Payments securely handled by Stripe · {{ config('app.name') }}
    </div>
</body>
</html>
