<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthwise Summary Report</title>
    <style>
        /* Force portrait A4 when rendered by DomPDF */
        @page { size: A4 portrait; margin: 15mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0 0 5px; }
        .header p { margin: 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; }
        th { background-color: #333; color: white; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { background-color: #f8f8f8; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Monthwise Summary for Received Payments</h1>
        <p>{{ $data['month_name'] }}</p>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>TOTAL (INCL. RETURNS)\n(RECEIPTS)</th>
                <th>Cash</th>
                <th>Cheque</th>
                <th>Cards</th>
                <th>Bank Transfer</th>
                <th>UPI Payment</th>
                <th>Product Return</th>
                <th>Overall Expenses (Month)</th>
                <th>Net After Expenses</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['date'] }}</td>
                    <td class="text-right"><strong>{{ number_format($row['grand_total'] ?? 0, 2) }}</strong></td>
                    <td class="text-right">{{ number_format($row['totals']['Cash'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['totals']['Cheque'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['totals']['Cards'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['totals']['Bank Transfer'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['totals']['UPI Payment'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['totals']['Product return'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row['overall_expenses'] ?? 0, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($row['net_after_expenses'] ?? ($row['grand_total'] ?? 0), 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">No data available for this period</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($data['rows']) > 0)
            <tfoot>
                <tr class="total-row">
                    <td class="text-center"><strong>Total</strong></td>
                    <td class="text-right"><strong>{{ number_format($data['grand_total'] ?? 0, 2) }}</strong></td>
                    <td class="text-right">{{ number_format($data['column_totals']['Cash'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($data['column_totals']['Cheque'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($data['column_totals']['Cards'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($data['column_totals']['Bank Transfer'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($data['column_totals']['UPI Payment'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($data['column_totals']['Product return'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($data['column_totals']['overall_expenses'] ?? 0, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($data['net_total'] ?? 0, 2) }}</strong></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
