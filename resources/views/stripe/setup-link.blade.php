<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
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
            max-width: 26rem;
        }
        h1 { font-size: 1.35rem; margin: 0 0 .75rem; }
        p { color: #55606e; line-height: 1.5; margin: 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
