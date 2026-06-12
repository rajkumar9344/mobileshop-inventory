<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customers Payment Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; margin: 0; padding: 15px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #333; color: white; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Customers Payment Report</h1>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    @if(!empty($filters['customer_id']) || !empty($filters['reference']) || !empty($filters['start_date']) || !empty($filters['end_date']))
        <div class="filters">
            <strong>Filters Applied:</strong>
            @if(!empty($filters['customer_name']))
                <span>Customer: {{ $filters['customer_name'] }}</span>
            @endif
            @if(!empty($filters['reference']))
                <span>Reference: {{ $filters['reference'] }}</span>
            @endif
            @if(!empty($filters['start_date']) || !empty($filters['end_date']))
                <span>Range: {{ $filters['start_date'] ?? '-' }} to {{ $filters['end_date'] ?? '-' }}</span>
            @endif
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Customer Name</th>
                <th>Sales Bill Ref No</th>
                <th>Sales Bill Date</th>
                <th>Sales Bill Overall Amount</th>
                <th>Received Amount</th>
                <th>Received Date</th>
                <th>Payment Mode</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $line)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $line->receipt->customer->customer_name ?? '-' }}</td>
                    <td class="text-center">{{ $line->sale->reference ?? $line->bill_ref ?? '-' }}</td>
                    <td class="text-center">{{ optional($line->sale && $line->sale->date ? \Carbon\Carbon::parse($line->sale->date) : null)->format('d-m-Y') ?? (optional($line->bill_date)->format('d-m-Y') ?? '-') }}</td>
                    <td class="text-right">{{ number_format($line->bill_amount ?? ($line->sale ? ($line->sale->overall_amount ?? $line->sale->total_amount ?? 0) : 0), 2) }}</td>
                    <td class="text-right">{{ number_format($line->payment_amount, 2) }}</td>
                    <td class="text-center">{{ optional($line->receipt && $line->receipt->date ? \Carbon\Carbon::parse($line->receipt->date) : null)->format('d-m-Y') }}</td>
                    <td class="text-center">{{ $line->receipt->payment_mode ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">No Payments Found!</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
