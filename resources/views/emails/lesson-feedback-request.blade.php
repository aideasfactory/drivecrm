<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Feedback</title>
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

        <p>Your driving lesson with <strong>{{ $instructorName }}</strong> has been completed. We hope it went well!</p>

        <div class="lesson-details">
            <p><strong>Date:</strong> {{ $lessonDate }}</p>
            @if($lessonTime)
                <p><strong>Time:</strong> {{ $lessonTime }}</p>
            @endif
            <p><strong>Instructor:</strong> {{ $instructorName }}</p>
        </div>

        <p>We would love to hear how your lesson went. Your feedback helps us ensure you receive the best learning experience.</p>

        <p>Thank you for choosing {{ config('app.name') }}!</p>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
