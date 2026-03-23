<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources Assigned</title>
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
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 24px;
            color: #1a1a1a;
            margin: 0;
        }
        h2 {
            font-size: 20px;
            color: #1a1a1a;
            margin-top: 0;
        }
        .lesson-details {
            background-color: #f9fafb;
            border-radius: 6px;
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
            color: #6b7280;
            background-color: #f3f4f6;
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
            background-color: #1a1a1a;
            color: #ffffff;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
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
        <div class="logo">
            <h1>{{ config('app.name') }}</h1>
        </div>

        <h2>Hi {{ $studentName }},</h2>

        <p>{{ $instructorName }} has assigned some resources to help you prepare for your lesson.</p>

        <div class="lesson-details">
            <p><strong>Lesson date:</strong> {{ $lessonDate }}</p>
            <p><strong>Instructor:</strong> {{ $instructorName }}</p>
        </div>

        <h3 style="font-size: 18px; margin-bottom: 16px;">Your Resources</h3>

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
</body>
</html>
