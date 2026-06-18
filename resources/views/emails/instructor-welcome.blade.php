<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $appName }}</title>
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
        .details {
            background-color: #fef2f2;
            border-left: 3px solid #DC2626;
            border-radius: 0 6px 6px 0;
            padding: 16px 20px;
            margin: 24px 0;
        }
        .details p {
            margin: 6px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .details .value {
            font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 600;
            word-break: break-all;
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
        ol {
            padding-left: 22px;
        }
        ol li {
            margin-bottom: 6px;
        }
        .fallback-link {
            margin-top: 18px;
            font-size: 12px;
            color: #6b7280;
            word-break: break-all;
        }
        .notice {
            margin-top: 24px;
            padding: 12px 16px;
            background-color: #fffbeb;
            border-left: 3px solid #d97706;
            border-radius: 0 6px 6px 0;
            font-size: 13px;
            color: #92400e;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
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
            <h2>Welcome to {{ $appName }}, {{ $instructorName }} 👋</h2>

            <p>An administrator has just created an instructor account for you on {{ $appName }}. To start managing your pupils, calendar and payouts, you'll need to set a password and sign in.</p>

            <p><strong>Here's what to do next:</strong></p>
            <ol>
                <li>Click the <strong>Set up your account</strong> button below.</li>
                <li>Choose a strong password on the page that opens.</li>
                <li>Sign in with your email and the password you just created.</li>
            </ol>

            <div class="details">
                <p><strong>Your sign-in email:</strong></p>
                <p class="value">{{ $email }}</p>
            </div>

            <p style="text-align: center;">
                <a class="button" href="{{ $setupUrl }}">Set up your account</a>
            </p>

            <p class="fallback-link">
                If the button doesn't work, copy and paste this link into your browser:<br>
                {{ $setupUrl }}
            </p>

            <div class="notice">
                For your security, this setup link will expire in {{ $expiresInMinutes }} minutes. If it expires before you've finished, just visit <a href="{{ $loginUrl }}">{{ $loginUrl }}</a> and choose <strong>Forgot password</strong> — we'll send a fresh link.
            </div>

            <p style="margin-top: 24px; font-size: 13px; color: #6b7280;">
                Didn't expect this email? You can safely ignore it — no account is active until the link above is used.
            </p>

            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
