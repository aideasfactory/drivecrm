<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .brand-bar {
            background-color: #DC2626;
            height: 4px;
        }
        .logo {
            text-align: center;
            padding: 30px 40px 0;
        }
        .logo img {
            width: 70px;
            height: 70px;
        }
        .email-body {
            padding: 20px 40px 40px;
        }
        h2 {
            font-size: 20px;
            color: #1a1a1a;
            margin-top: 0;
        }
        p {
            margin: 12px 0;
        }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 22px;
            background: #DC2626;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        .button-wrap {
            text-align: center;
            margin: 8px 0 16px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
        }
        .content a {
            color: #DC2626;
        }
        .content ul, .content ol {
            padding-left: 22px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="brand-bar"></div>
        <div class="logo">
            <img src="{{ asset('logo.png') }}" alt="{{ $appName }}">
        </div>

        <div class="email-body">
            @if (! empty($greeting))
                <h2>{{ $greeting }}</h2>
            @endif

            @if (! empty($hasActionSplit))
                <div class="content">{!! $bodyHtmlBefore !!}</div>
            @else
                <div class="content">{!! $bodyHtml !!}</div>
            @endif

            @if (! empty($actionText) && ! empty($actionUrl))
                <p class="button-wrap">
                    <a class="button" href="{{ $actionUrl }}">{{ $actionText }}</a>
                </p>
            @endif

            @if (! empty($hasActionSplit) && ! empty($bodyHtmlAfter))
                <div class="content">{!! $bodyHtmlAfter !!}</div>
            @endif

            @if (! empty($salutationHtml))
                <div class="content">{!! $salutationHtml !!}</div>
            @endif

            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
                @include('emails.partials.legal-footer')
            </div>
        </div>
    </div>
</body>
</html>
