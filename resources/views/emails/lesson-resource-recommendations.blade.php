<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommended Resources</title>
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
        .lesson-details {
            background-color: #fef2f2;
            border-left: 3px solid #DC2626;
            border-radius: 0 6px 6px 0;
            padding: 16px;
            margin: 20px 0;
        }
        .lesson-details p {
            margin: 4px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .lesson-details strong {
            color: #1a1a1a;
        }
        .resource-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 16px;
            margin: 12px 0;
        }
        .resource-card h3 {
            font-size: 16px;
            color: #1a1a1a;
            margin: 0 0 4px 0;
        }
        .resource-type {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            color: #DC2626;
            background-color: #fef2f2;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .resource-description {
            font-size: 14px;
            color: #6b7280;
            margin: 8px 0 12px 0;
        }
        .resource-link {
            display: inline-block;
            background-color: #DC2626;
            color: #ffffff;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        a {
            color: #DC2626;
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
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }}">
        </div>

        <div class="email-body">
            <h2>Hi {{ $studentName }},</h2>

            <p>Based on your recent driving lesson, we've picked some resources to help you practise and improve.</p>

            <div class="lesson-details">
                <p><strong>Date:</strong> {{ $lessonDate }}</p>
                <p><strong>Instructor:</strong> {{ $instructorName }}</p>
                @if($summaryExcerpt)
                    <p><strong>Covered:</strong> {{ $summaryExcerpt }}</p>
                @endif
            </div>

            <h3 style="font-size: 18px; margin-bottom: 16px;">Recommended Resources</h3>

            @foreach($resourceLinks as $resource)
                <div class="resource-card">
                    <span class="resource-type">{{ $resource['type'] }}</span>
                    <h3>{{ $resource['title'] }}</h3>
                    @if($resource['description'])
                        <p class="resource-description">{{ $resource['description'] }}</p>
                    @endif
                    <a href="{{ $resource['url'] }}" class="resource-link">View Resource</a>
                </div>
            @endforeach

            <p style="margin-top: 24px; font-size: 14px; color: #6b7280;">
                These links are valid for 7 days. If they have expired, please contact your instructor for access.
            </p>

            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
