<table>
    <thead>
        <tr>
            <th colspan="10" style="font-size: 16px; font-weight: bold; text-align: center; background-color: #ffffff; color: #1f2937; padding: 8px;">Monthwise Summary for Received Payments - {{ $data['month_name'] }}</th>
        </tr>
        <tr><th></th></tr>
        <tr>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Date</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">TOTAL (INCL. RETURNS)\n(RECEIPTS)</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Cash</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Cheque</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Cards</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Bank Transfer</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">UPI Payment</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Product Return</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Overall Expenses (Month)</th>
            <th style="background-color: #f3f6f9; color: #1f2937; font-weight: bold;">Net After Expenses</th>
        </tr>
    </thead>
    <tbody>
        @forelse($data['rows'] as $row)
            <tr>
                <td style="text-align: center;">{{ $row['date'] }}</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($row['grand_total'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['totals']['Cash'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['totals']['Cheque'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['totals']['Cards'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['totals']['Bank Transfer'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['totals']['UPI Payment'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['totals']['Product return'] ?? 0, 2) }}</td>
                <td style="text-align: right;">{{ number_format($row['overall_expenses'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($row['net_after_expenses'] ?? ($row['grand_total'] ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center;">No data available for this period</td>
            </tr>
        @endforelse
        @if(count($data['rows']) > 0)
            <tr>
                <td style="text-align: center; font-weight: bold; background-color: #f8f8f8;">Total</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['grand_total'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['Cash'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['Cheque'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['Cards'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['Bank Transfer'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['UPI Payment'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['Product return'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['column_totals']['overall_expenses'] ?? 0, 2) }}</td>
                <td style="text-align: right; font-weight: bold; background-color: #f8f8f8;">{{ number_format($data['net_total'] ?? 0, 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>
