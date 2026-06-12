<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Payment Report</title>
    @include('reports.partials._print-styles')
    <style>
        table { table-layout: fixed; }
        colgroup col.col-no    { width: 4%; }
        colgroup col.col-cust  { width: 16%; }
        colgroup col.col-ref   { width: 18%; }
        colgroup col.col-bdate { width: 9%; }
        colgroup col.col-bamt  { width: 10%; }
        colgroup col.col-rcpt  { width: 10%; }
        colgroup col.col-settl { width: 6%; }
        colgroup col.col-recv  { width: 10%; }
        colgroup col.col-rdate { width: 9%; }
        colgroup col.col-mode  { width: 8%; }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
        <button class="btn-close2" onclick="window.close()">&#x2715;&nbsp; Close</button>
        <span class="print-tip" style="font-size:11px;color:#888;margin-left:12px;">&#9432; In the print dialog, uncheck <strong>"Headers and footers"</strong> to hide the URL.</span>
        <span class="record-count">Total Records: <strong>{{ $payments->count() }}</strong></span>
    </div>

    <div class="report-header">
        <h2>Customers Payment Report</h2>
        <p class="meta">Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>

    @if(!empty($filters['customer_name']) || !empty($filters['reference']) || !empty($filters['start_date']) || !empty($filters['end_date']) || !empty($filters['payment_mode']))
    <div class="filters-bar">
        <strong>Filters Applied &mdash;</strong>
        @if(!empty($filters['customer_name']))
            <span>Customer: <strong>{{ $filters['customer_name'] }}</strong></span>
        @endif
        @if(!empty($filters['reference']))
            <span>Bill Ref: <strong>{{ $filters['reference'] }}</strong></span>
        @endif
        @if(!empty($filters['start_date']) || !empty($filters['end_date']))
            <span>Period: <strong>{{ $filters['start_date'] ?? '-' }} to {{ $filters['end_date'] ?? '-' }}</strong></span>
        @endif
        @if(!empty($filters['payment_mode']))
            <span>Payment Mode: <strong>{{ $filters['payment_mode'] }}</strong></span>
        @endif
    </div>
    @endif

    <table>
        <colgroup>
            <col class="col-no">
            <col class="col-cust">
            <col class="col-ref">
            <col class="col-bdate">
            <col class="col-bamt">
            <col class="col-rcpt">
            <col class="col-settl">
            <col class="col-recv">
            <col class="col-rdate">
            <col class="col-mode">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer Name</th>
                <th>Sales Bill Ref No</th>
                <th>Bill Date</th>
                <th>Bill Amount</th>
                <th>Receipt Ref</th>
                <th>Settled</th>
                <th>Received Amount</th>
                <th>Received Date</th>
                <th>Payment Mode</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $line)
            <tr>
                <td class="t-center">{{ $loop->iteration }}</td>
                <td class="t-left">{{ $line->receipt->customer->customer_name ?? '-' }}</td>
                <td class="t-center">{{ $line->sale->reference ?? $line->bill_ref ?? '-' }}</td>
                <td class="t-center">{{ optional($line->sale && $line->sale->date ? \Carbon\Carbon::parse($line->sale->date) : null)->format('d-m-Y') ?? (optional($line->bill_date)->format('d-m-Y') ?? '-') }}</td>
                <td class="t-right">{{ number_format($line->bill_amount ?? ($line->sale->overall_amount ?? $line->sale->total_amount ?? 0), 2) }}</td>
                <td class="t-center">{{ $line->receipt->reference ?? ($line->receipt ? 'RE'.str_pad($line->receipt->id, 5, '0', STR_PAD_LEFT) : '-') }}</td>
                <td class="t-center">{{ $line->is_settled ? 'Yes' : 'No' }}</td>
                <td class="t-right">{{ number_format($line->payment_amount, 2) }}</td>
                <td class="t-center">{{ optional($line->receipt && $line->receipt->date ? \Carbon\Carbon::parse($line->receipt->date) : null)->format('d-m-Y') ?? '-' }}</td>
                <td class="t-center">{{ $line->receipt->payment_mode ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="t-center" style="padding:20px;color:#888;">No payment records found.</td>
            </tr>
            @endforelse
            @if($payments->count() > 0)
            <tr style="background-color:#e8e8e8;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                <td colspan="7" class="t-right" style="border:1px solid #999;">Total Received Amount:</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($payments->sum('payment_amount'), 2) }}</td>
                <td colspan="2" style="border:1px solid #999;"></td>
            </tr>
            @endif
        </tbody>
        </table>

    <div class="report-footer">
        Customers Payment Report &bull; Printed on {{ date('d-m-Y H:i:s') }} &bull; Total {{ $payments->count() }} record(s)
    </div>

</body>
</html>
