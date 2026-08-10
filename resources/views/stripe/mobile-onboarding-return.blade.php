<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stripe Setup — {{ config('app.name') }}</title>
    <style>
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f6f8fa;
            color: #1a1a2e;
        }
        .card {
            text-align: center;
            padding: 2.5rem 2rem;
            max-width: 22rem;
        }
        h1 { font-size: 1.35rem; margin: 0 0 .75rem; }
        p { color: #55606e; line-height: 1.5; margin: 0 0 1.5rem; }
        a.button {
            display: inline-block;
            background: #1a1a2e;
            color: #fff;
            text-decoration: none;
            padding: .75rem 1.75rem;
            border-radius: .5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Stripe setup finished</h1>
        <p>You're done here — return to the app to carry on. If nothing happens automatically, tap the button below or close this window.</p>
        <a class="button" href="{{ $deepLink }}">Return to app</a>
    </div>
    <script>
        window.location.href = @js($deepLink);
    </script>
</body>
</html>
