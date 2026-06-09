<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Operations Report</title>
    @include('reports.partials._print-styles')
    <style>
        .section-title { background-color: #1a1a2e; color: #fff; padding: 7px 10px; font-size: 12px; font-weight: bold; margin-top: 18px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .kpi-table { margin-bottom: 6px; }
        .kpi-table td { border: 1px solid #d0d0d0; padding: 7px 10px; font-size: 11.5px; vertical-align: middle; }
        .kpi-label { color: #555; font-weight: bold; width: 20%; }
        .kpi-value { font-size: 13px; font-weight: bold; text-align: right; width: 5%; }
        .kpi-highlight td { background-color: #f0f7ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead tr th { background-color: #333; border-color: #555; }
        .row-total td { background-color: #e8e8e8 !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
        <button class="btn-close2" onclick="window.close()">&#x2715;&nbsp; Close</button>
        <span class="print-tip" style="font-size:11px;color:#888;margin-left:12px;">&#9432; In the print dialog, uncheck <strong>"Headers and footers"</strong> to hide the URL.</span>
    </div>

    <div class="report-header">
        <h2>Daily Operations Report</h2>
        <p class="meta">Report Date: {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }} &bull; Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>

    {{-- ── Daily Closing Summary ── --}}
    <div class="section-title">Daily Closing Summary</div>
    <table class="kpi-table">
        <tbody>
            <tr>
                <td class="kpi-label">Opening Balance</td>
                <td class="kpi-value">{{ number_format($data['opening_balance'], 2) }}</td>
                <td class="kpi-label">Sales Gross (MRP)</td>
                <td class="kpi-value">{{ number_format($data['daily_sales_gross'], 2) }}</td>
            </tr>
            <tr>
                <td class="kpi-label">Sales Net Amount</td>
                <td class="kpi-value">{{ number_format($data['daily_sales_net'], 2) }}</td>
                <td class="kpi-label">Received Amount</td>
                <td class="kpi-value">{{ number_format($data['daily_received'], 2) }}</td>
            </tr>
            <tr>
                <td class="kpi-label">Daily Expenses</td>
                <td class="kpi-value text-danger">{{ number_format($data['daily_expenses'], 2) }}</td>
                <td class="kpi-label">Net Closing (Before Petty)</td>
                <td class="kpi-value">{{ number_format($data['net_closing_before_petty'], 2) }}</td>
            </tr>
            <tr class="kpi-highlight">
                <td class="kpi-label">Tomorrow Opening</td>
                <td class="kpi-value">{{ number_format($data['tomorrow_opening'], 2) }}</td>
                <td class="kpi-label">Closing Balance</td>
                <td class="kpi-value text-success" style="font-size:14px;">{{ number_format($data['closing_balance'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── Payment Method Totals ── --}}
    <div class="section-title">Payment Method Totals</div>
    <table>
        <thead>
            <tr>
                <th>Payment Method</th>
                <th class="t-right">Before Expense</th>
                <th class="t-right">Expense</th>
                <th class="t-right">After Expense</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['payment_totals']['methods'] as $mode => $totals)
            <tr>
                <td>{{ $mode }}</td>
                <td class="t-right">{{ number_format($totals['before_expense'], 2) }}</td>
                <td class="t-right text-danger">{{ number_format($totals['expense'], 2) }}</td>
                <td class="t-right">{{ number_format($totals['after_expense'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="row-total">
                <td>Total (excl. Product Return)</td>
                <td class="t-right">{{ number_format($data['payment_totals']['total_before_expense'], 2) }}</td>
                <td class="t-right">{{ number_format($data['daily_expenses'], 2) }}</td>
                <td class="t-right">{{ number_format($data['payment_totals']['total_after_expense'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ── Receipts by Payment Method ── --}}
    @foreach($data['receipts_by_method'] as $mode => $receipts)
        @if(count($receipts) > 0)
        <div class="section-title">{{ $mode }} Receipts ({{ count($receipts) }})</div>
        <table>
            <thead>
                <tr>
                    <th style="width:20%">Receipt Ref</th>
                    <th style="width:35%">Payer</th>
                    <th style="width:15%">Date</th>
                    <th style="width:15%" class="t-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipts as $receipt)
                <tr>
                    <td>{{ $receipt['reference'] }}</td>
                    <td>{{ $receipt['customer_name'] }}</td>
                    <td class="t-center">{{ $receipt['date'] }}</td>
                    <td class="t-right">{{ number_format($receipt['total_amount'] ?? $receipt['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    @endforeach

    {{-- ── Expenses by Payment Mode ── --}}
    @if(count($data['expenses_by_mode']) > 0)
    <div class="section-title">Expenses by Payment Mode</div>
    <table>
        <thead>
            <tr>
                <th>Payment Mode</th>
                <th class="t-right">Count</th>
                <th class="t-right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['expenses_by_mode'] as $mode => $modeData)
            <tr>
                <td>{{ $mode ?? 'Cash' }}</td>
                <td class="t-right">{{ $modeData['count'] }}</td>
                <td class="t-right">{{ number_format($modeData['total'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="2">Total Expenses</td>
                <td class="t-right">{{ number_format($data['daily_expenses'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="report-footer">
        Daily Operations Report &bull; {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }} &bull; Printed on {{ date('d-m-Y H:i:s') }}
    </div>

</body>
</html>
