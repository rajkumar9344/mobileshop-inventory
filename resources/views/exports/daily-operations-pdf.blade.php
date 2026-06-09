<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Operations Report</title>
    <style>
        /* Force portrait A4 when rendered by DomPDF */
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0 0 5px; }
        .header p { margin: 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #333; color: white; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-title { background-color: #4a90d9; color: white; padding: 8px; margin-top: 20px; font-weight: bold; }
        .kpi-table td { padding: 10px; }
        .kpi-label { font-weight: bold; color: #666; }
        .kpi-value { font-size: 14px; font-weight: bold; }
        .highlight { background-color: #f0f0f0; }
        .total-row { background-color: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daily Operations Report</h1>
        <p>Report Date: {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</p>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    {{-- Daily Closing Summary --}}
    <div class="section-title">Daily Closing Summary</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-label">Opening Balance</td>
            <td class="kpi-value text-right">{{ number_format($data['opening_balance'], 2) }}</td>
            <td class="kpi-label">Sales Gross (MRP)</td>
            <td class="kpi-value text-right">{{ number_format($data['daily_sales_gross'], 2) }}</td>
        </tr>
        <tr>
            <td class="kpi-label">Sales Net Amount</td>
            <td class="kpi-value text-right">{{ number_format($data['daily_sales_net'], 2) }}</td>
            <td class="kpi-label">Received Amount</td>
            <td class="kpi-value text-right">{{ number_format($data['daily_received'], 2) }}</td>
        </tr>
        <tr>
            <td class="kpi-label">Daily Expenses</td>
            <td class="kpi-value text-right" style="color: #d9534f;">{{ number_format($data['daily_expenses'], 2) }}</td>
            <td class="kpi-label">Net Closing (Before Petty)</td>
            <td class="kpi-value text-right">{{ number_format($data['net_closing_before_petty'], 2) }}</td>
        </tr>
        <tr class="highlight">
            <td class="kpi-label">Tomorrow Opening</td>
            <td class="kpi-value text-right">{{ number_format($data['tomorrow_opening'], 2) }}</td>
            <td class="kpi-label">Closing Balance</td>
            <td class="kpi-value text-right" style="font-size: 16px; color: #5cb85c;">{{ number_format($data['closing_balance'], 2) }}</td>
        </tr>
    </table>

    {{-- Payment Method Totals --}}
    <div class="section-title">Payment Method Totals</div>
    <table>
        <thead>
            <tr>
                <th>Payment Method</th>
                <th class="text-right">Before Expense</th>
                <th class="text-right">Expense</th>
                <th class="text-right">After Expense</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['payment_totals']['methods'] as $mode => $totals)
                <tr>
                    <td>{{ $mode }}</td>
                    <td class="text-right">{{ number_format($totals['before_expense'], 2) }}</td>
                    <td class="text-right" style="color: #d9534f;">{{ number_format($totals['expense'], 2) }}</td>
                    <td class="text-right">{{ number_format($totals['after_expense'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total (excl. Product Return)</td>
                <td class="text-right">{{ number_format($data['payment_totals']['total_before_expense'], 2) }}</td>
                <td class="text-right">{{ number_format($data['daily_expenses'], 2) }}</td>
                <td class="text-right">{{ number_format($data['payment_totals']['total_after_expense'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Receipts by Payment Method --}}
    @foreach($data['receipts_by_method'] as $mode => $receipts)
        @if(count($receipts) > 0)
            <div class="section-title">{{ $mode }} Receipts ({{ count($receipts) }})</div>
            <table>
                <thead>
                    <tr>
                        <th>Receipt Ref</th>
                        <th>Payer</th>
                        <th>Date</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt['reference'] }}</td>
                            <td>{{ $receipt['customer_name'] }}</td>
                            <td>{{ $receipt['date'] }}</td>
                            <td class="text-right">{{ number_format($receipt['total_amount'] ?? $receipt['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    {{-- Expenses by Payment Mode --}}
    @if(count($data['expenses_by_mode']) > 0)
        <div class="section-title">Expenses by Payment Mode</div>
        <table>
            <thead>
                <tr>
                    <th>Payment Mode</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['expenses_by_mode'] as $mode => $modeData)
                    <tr>
                        <td>{{ $mode ?? 'Cash' }}</td>
                        <td class="text-right">{{ $modeData['count'] }}</td>
                        <td class="text-right">{{ number_format($modeData['total'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2">Total Expenses</td>
                    <td class="text-right">{{ number_format($data['daily_expenses'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
