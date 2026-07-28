<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your account is scheduled for deletion</title>
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
        .highlight {
            background-color: #fef2f2;
            border-left: 3px solid #DC2626;
            border-radius: 0 6px 6px 0;
            padding: 16px 20px;
            margin: 24px 0;
        }
        .highlight p {
            margin: 6px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .highlight .value {
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 600;
        }
        .button {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 18px;
            background: #DC2626;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
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
            <h2>Hi {{ $firstName }},</h2>

            <p>We've received a request to delete your {{ $appName }} account.</p>

            <div class="highlight">
                <p><strong>Your account is scheduled for deletion on:</strong></p>
                <p class="value">{{ $deletionDate }}</p>
            </div>

            <p>Until then, nothing changes — you can keep using your account as normal. If you change your mind, open the app and cancel the request from Settings at any time before that date.</p>

            <p>After {{ $deletionDate }}, your account and personal data will be permanently deleted and cannot be recovered.</p>

            <div class="notice">
                If you didn't request this, someone else may have access to your account. Sign in and cancel the deletion request from Settings straight away, then change your password.
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
