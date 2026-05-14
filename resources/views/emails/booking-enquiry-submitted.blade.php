<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New booking enquiry</title>
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
        .content {
            padding: 32px 40px;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 16px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .status-in {
            background: #DCFCE7;
            color: #166534;
        }
        .status-out {
            background: #F3F4F6;
            color: #4B5563;
        }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        table.details th,
        table.details td {
            text-align: left;
            padding: 10px 0;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: top;
            font-size: 14px;
        }
        table.details th {
            width: 130px;
            color: #6B7280;
            font-weight: 500;
        }
        .button {
            display: inline-block;
            margin-top: 24px;
            padding: 10px 18px;
            background: #111827;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        .meta {
            margin-top: 24px;
            font-size: 12px;
            color: #9CA3AF;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="brand-bar"></div>
        <div class="content">
            <h1>New booking enquiry</h1>

            <p>
                @if ($inArea)
                    <span class="status-badge status-in">In area</span>
                    The lead's postcode is covered &mdash; please follow up.
                @else
                    <span class="status-badge status-out">Out of area</span>
                    The lead's postcode is outside the configured coverage area.
                @endif
            </p>

            <table class="details">
                <tr>
                    <th>Name</th>
                    <td>{{ trim(($firstName ?? '').' '.($lastName ?? '')) ?: '—' }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>
                        @if ($email)
                            <a href="mailto:{{ $email }}">{{ $email }}</a>
                        @else
                            &mdash;
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td>{{ $phone ?: '—' }}</td>
                </tr>
                <tr>
                    <th>Postcode</th>
                    <td>{{ $postcode ?: '—' }}</td>
                </tr>
                <tr>
                    <th>Instructor ID</th>
                    <td>{{ $instructorId ?: '—' }}</td>
                </tr>
                <tr>
                    <th>Submitted</th>
                    <td>{{ $submittedAt ?: '—' }}</td>
                </tr>
            </table>

            <a class="button" href="{{ $enquiriesUrl }}">View all enquiries</a>

            <p class="meta">Enquiry ID: {{ $enquiryId }}</p>
        </div>
    </div>
</body>
</html>
