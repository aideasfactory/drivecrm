<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your {{ $taxYearLabel }} tax-year archive is ready</title>
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
        .brand-bar { background-color: #DC2626; height: 4px; }
        .content { padding: 32px 40px; }
        h1 { font-size: 20px; margin: 0 0 16px; }
        h2 { font-size: 14px; margin: 24px 0 8px; }
        p { margin: 0 0 12px; }
        ul { padding-left: 18px; margin: 0 0 16px; }
        li { margin: 4px 0; }
        .button-wrap { text-align: center; margin: 28px 0; }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #DC2626;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        .meta {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .meta dt { font-size: 12px; color: #6b7280; }
        .meta dd { margin: 0 0 6px; font-weight: 600; }
        .small { font-size: 12px; color: #6b7280; }
        .footer { padding: 16px 40px 24px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="brand-bar"></div>
        <div class="content">
            <h1>Your {{ $taxYearLabel }} tax-year archive is ready</h1>

            <p>
                @if ($firstName)Hi {{ $firstName }},@else Hi,@endif
            </p>

            <p>
                Your tax-year archive ZIP has been built and is ready to download.
                The link below is valid for the next {{ $linkExpiresAt->diffForHumans(null, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} (until {{ $linkExpiresAt->format('j M Y, H:i') }} UK time).
            </p>

            <div class="button-wrap">
                <a class="button" href="{{ $downloadUrl }}">Download archive</a>
            </div>

            <h2>What's inside</h2>
            <div class="meta">
                <dl>
                    @if ($fileSizeMb)<dt>File size</dt><dd>{{ $fileSizeMb }} MB</dd>@endif
                    <dt>Finance rows</dt><dd>{{ number_format($financeRows) }}</dd>
                    <dt>Mileage entries</dt><dd>{{ number_format($mileageRows) }}</dd>
                    <dt>Receipt files</dt><dd>{{ number_format($receipts) }}</dd>
                    <dt>HMRC submissions</dt><dd>{{ number_format($submissions) }}</dd>
                </dl>
            </div>

            <ul>
                <li><code>finances.csv</code> — every payment and expense for the tax year</li>
                <li><code>mileage.csv</code> — every mileage log entry</li>
                <li><code>receipts/Q1–Q4/</code> — all receipt files, bucketed by quarter</li>
                <li><code>submissions/</code> — each HMRC submission with request + response payload</li>
                <li><code>summary.pdf</code> — human-readable cover sheet</li>
            </ul>

            <p class="small">
                If the link above has expired, open <a href="{{ url('/hmrc/archive') }}">Year-end archives</a> in DRIVE and click "Email link"
                to receive a fresh one. Archives are retained for {{ $retentionYears }} years per HMRC requirements.
            </p>
        </div>
        <div class="footer">
            Sent by DRIVE — your driving-instructor admin platform.
        </div>
    </div>
</body>
</html>
