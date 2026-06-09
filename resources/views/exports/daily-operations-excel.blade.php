<table>
    <thead>
        <tr>
            <th colspan="4" style="font-size: 16px; font-weight: bold; text-align: center;">Daily Operations Report - {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</th>
        </tr>
        <tr><th></th></tr>
    </thead>
</table>

{{-- Daily Closing Summary --}}
<table>
    <thead>
        <tr>
            <th colspan="4" style="background-color: #4a90d9; color: white; font-weight: bold;">Daily Closing Summary</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Opening Balance</strong></td>
            <td style="text-align: right;">{{ number_format($data['opening_balance'], 2) }}</td>
            <td><strong>Sales Gross (MRP)</strong></td>
            <td style="text-align: right;">{{ number_format($data['daily_sales_gross'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Sales Net Amount</strong></td>
            <td style="text-align: right;">{{ number_format($data['daily_sales_net'], 2) }}</td>
            <td><strong>Received Amount</strong></td>
            <td style="text-align: right;">{{ number_format($data['daily_received'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Daily Expenses</strong></td>
            <td style="text-align: right;">{{ number_format($data['daily_expenses'], 2) }}</td>
            <td><strong>Net Closing (Before Petty)</strong></td>
            <td style="text-align: right;">{{ number_format($data['net_closing_before_petty'], 2) }}</td>
        </tr>
        <tr>
            <td><strong>Tomorrow Opening</strong></td>
            <td style="text-align: right;">{{ number_format($data['tomorrow_opening'], 2) }}</td>
            <td><strong>Closing Balance</strong></td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($data['closing_balance'], 2) }}</td>
        </tr>
    </tbody>
</table>

<table><tr><td></td></tr></table>

{{-- Payment Method Totals --}}
<table>
    <thead>
        <tr>
            <th colspan="4" style="background-color: #4a90d9; color: white; font-weight: bold;">Payment Method Totals</th>
        </tr>
        <tr>
            <th>Payment Method</th>
            <th style="text-align: right;">Before Expense</th>
            <th style="text-align: right;">Expense</th>
            <th style="text-align: right;">After Expense</th>
        </tr>
    </thead>
    <tbody>
        @foreach($data['payment_totals']['methods'] as $mode => $totals)
            <tr>
                <td>{{ $mode }}</td>
                <td style="text-align: right;">{{ number_format($totals['before_expense'], 2) }}</td>
                <td style="text-align: right;">{{ number_format($totals['expense'], 2) }}</td>
                <td style="text-align: right;">{{ number_format($totals['after_expense'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td><strong>Total (excl. Product Return)</strong></td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($data['payment_totals']['total_before_expense'], 2) }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($data['daily_expenses'], 2) }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($data['payment_totals']['total_after_expense'], 2) }}</td>
        </tr>
    </tbody>
</table>

<table><tr><td></td></tr></table>

{{-- Receipts by Payment Method --}}
@foreach($data['receipts_by_method'] as $mode => $receipts)
    @if(count($receipts) > 0)
        <table>
            <thead>
                <tr>
                    <th colspan="4" style="background-color: #5cb85c; color: white; font-weight: bold;">{{ $mode }} Receipts ({{ count($receipts) }})</th>
                </tr>
                <tr>
                    <th>Receipt Ref</th>
                    <th>Payer</th>
                    <th>Date</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipts as $receipt)
                    <tr>
                        <td>{{ $receipt['reference'] }}</td>
                        <td>{{ $receipt['customer_name'] }}</td>
                        <td>{{ $receipt['date'] }}</td>
                            <td style="text-align: right;">{{ number_format($receipt['total_amount'] ?? $receipt['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table><tr><td></td></tr></table>
    @endif
@endforeach

{{-- Expenses by Payment Mode --}}
@if(count($data['expenses_by_mode']) > 0)
    <table>
        <thead>
            <tr>
                <th colspan="3" style="background-color: #d9534f; color: white; font-weight: bold;">Expenses by Payment Mode</th>
            </tr>
            <tr>
                <th>Payment Mode</th>
                <th style="text-align: right;">Count</th>
                <th style="text-align: right;">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['expenses_by_mode'] as $mode => $modeData)
                <tr>
                    <td>{{ $mode ?? 'Cash' }}</td>
                    <td style="text-align: right;">{{ $modeData['count'] }}</td>
                    <td style="text-align: right;">{{ number_format($modeData['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2"><strong>Total Expenses</strong></td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($data['daily_expenses'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif
