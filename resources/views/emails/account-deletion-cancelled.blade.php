<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your deletion request has been cancelled</title>
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
            background-color: #f0fdf4;
            border-left: 3px solid #16a34a;
            border-radius: 0 6px 6px 0;
            padding: 16px 20px;
            margin: 24px 0;
            font-size: 14px;
            color: #166534;
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

            <p>Your {{ $appName }} account deletion request has been cancelled.</p>

            <div class="highlight">
                Your account is safe — no data has been deleted, and everything will continue to work as normal.
            </div>

            <p>If you didn't cancel this request yourself, please change your password and contact support.</p>

            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
