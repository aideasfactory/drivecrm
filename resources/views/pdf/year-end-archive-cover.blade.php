<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax year archive — {{ $taxYearLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; line-height: 1.4; }
        h1 { font-size: 18pt; margin: 0 0 4px 0; }
        h2 { font-size: 13pt; margin: 18px 0 6px 0; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        .muted { color: #555; }
        .row { display: block; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; font-size: 10pt; }
        th { background: #f2f2f2; }
        .right { text-align: right; }
        .small { font-size: 9pt; color: #555; }
        .footer { margin-top: 30px; font-size: 9pt; color: #555; }
    </style>
</head>
<body>

    <h1>Tax year archive — {{ $taxYearLabel }}</h1>
    <div class="muted">{{ $taxYearStart->toFormattedDateString() }} to {{ $taxYearEnd->toFormattedDateString() }}</div>
    <div class="muted">Generated {{ $generatedAt->toFormattedDateString() }} at {{ $generatedAt->format('H:i') }} UK time</div>

    <h2>Instructor</h2>
    <div class="row"><strong>Name:</strong> {{ $instructorName }}</div>
    <div class="row"><strong>Email:</strong> {{ $instructorEmail }}</div>
    @if ($utr)<div class="row"><strong>UTR:</strong> {{ $utr }}</div>@endif
    @if ($nino)<div class="row"><strong>NI number:</strong> {{ $nino }}</div>@endif

    <h2>Headline figures</h2>
    <table>
        <tr><td>Total turnover (completed lessons)</td><td class="right">£{{ number_format($turnoverPence / 100, 2) }}</td></tr>
        <tr><td>Total expenses (all rows, includes excluded-from-HMRC categories)</td><td class="right">£{{ number_format($totalExpensesPence / 100, 2) }}</td></tr>
        <tr><td>Total business miles</td><td class="right">{{ number_format($totalBusinessMiles) }}</td></tr>
    </table>

    <h2>Expenses by HMRC bucket</h2>
    <table>
        <thead>
            <tr>
                <th>HMRC bucket</th>
                <th class="right">Total (£)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bucketTotals as $bucket => $pence)
                <tr>
                    <td>{{ $bucket }}</td>
                    <td class="right">£{{ number_format($pence / 100, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="small">Method-dependent rows (fuel, vehicle insurance, MOT, etc.) on a Simplified vehicle are recorded but excluded from the HMRC totals above. They are still in <code>finances.csv</code>.</div>

    <h2>Vehicles</h2>
    @if (count($vehicles) === 0)
        <div class="muted">No vehicles on file for this tax year.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Display name</th>
                    <th>Registration</th>
                    <th>Method</th>
                    <th class="right">Business use %</th>
                    <th>Acquired</th>
                    <th>Disposed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicles as $vehicle)
                    <tr>
                        <td>{{ $vehicle['display_name'] }}</td>
                        <td>{{ $vehicle['registration'] ?: '—' }}</td>
                        <td>{{ $vehicle['method_label'] }}</td>
                        <td class="right">{{ number_format((float) $vehicle['business_use_percentage'], 1) }}%</td>
                        <td>{{ $vehicle['acquired_on'] ?: '—' }}</td>
                        <td>{{ $vehicle['disposed_on'] ?: 'active' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>HMRC submissions in this tax year</h2>
    @if (count($submissions) === 0)
        <div class="muted">No HMRC submissions on file for this tax year.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Kind</th>
                    <th>Period</th>
                    <th>Submitted</th>
                    <th>Submission ID</th>
                    <th>Correlation ID</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($submissions as $submission)
                    <tr>
                        <td>{{ $submission['kind'] }}</td>
                        <td>{{ $submission['period'] }}</td>
                        <td>{{ $submission['submitted_at'] }}</td>
                        <td class="small">{{ $submission['submission_id'] ?: '—' }}</td>
                        <td class="small">{{ $submission['correlation_id'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>What's in this ZIP</h2>
    <ul>
        <li><code>finances.csv</code> — {{ $counts['finances'] ?? 0 }} payment / expense rows</li>
        <li><code>mileage.csv</code> — {{ $counts['mileage_logs'] ?? 0 }} mileage entries</li>
        <li><code>receipts/Q1..Q4/</code> — {{ $counts['receipts'] ?? 0 }} receipt files</li>
        <li><code>submissions/itsa/</code> and <code>submissions/vat/</code> — {{ $counts['submissions'] ?? 0 }} HMRC submission JSON files (request, response, correlation IDs, revision history)</li>
        <li><code>summary.pdf</code> — this cover sheet</li>
    </ul>

    <div class="footer">
        Generated by DRIVE for HMRC's 6-year MTD retention requirement. This is a snapshot at generation time — later edits to underlying records are not reflected. Regenerate if you need a refreshed copy.
    </div>

</body>
</html>
