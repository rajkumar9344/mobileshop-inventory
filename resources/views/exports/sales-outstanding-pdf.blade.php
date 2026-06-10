<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Outstanding Report</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }
        .header p {
            margin: 0;
            color: #666;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }
        td {
            font-size: 10px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-danger {
            color: #dc3545;
        }
        .text-warning {
            color: #ffc107;
        }
        .font-weight-bold {
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        tfoot tr {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .filters {
            margin-bottom: 15px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 10px;
        }
        .filters span {
            margin-right: 15px;
        }
        .aging-danger {
            color: #dc3545;
            font-weight: bold;
        }
        .aging-warning {
            color: #856404;
            font-weight: bold;
        }
        .aging-info {
            color: #0c5460;
        }
        .total-row {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sales Bills Outstanding Report</h1>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    @if(!empty($filters['customer_id']) || !empty($filters['reference']) || !empty($filters['aging_range']))
        <div class="filters">
            <strong>Filters Applied:</strong>
            @if(!empty($filters['customer_name']))
                <span>Customer: {{ $filters['customer_name'] }}</span>
            @endif
            @if(!empty($filters['reference']))
                <span>Reference: {{ $filters['reference'] }}</span>
            @endif
            @if(!empty($filters['aging_range']))
                <span>Aging: {{ $filters['aging_range'] }} days</span>
            @endif
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Sales Bill Ref No</th>
                <th>Bill Date</th>
                <th>Bill Overall Amount</th>
                <th>Received Amount</th>
                <th>Balance Amount</th>
                <th>Aging (Days)</th>
            </tr>
        </thead>
        <tbody>
            @php $totalBill = 0; $totalPaid = 0; $totalBalance = 0; @endphp
            @forelse($sales as $sale)
                @php
                    $agingDays = \App\Exports\SalesOutstandingExport::calculateAging($sale->date);
                    $billAmount = $sale->overall_net_rate ?? $sale->overall_amount ?? $sale->total_amount ?? 0;
                    $paidAmount = $sale->paid_amount ?? 0;
                    $balanceAmount = $sale->due_amount ?? ($billAmount - $paidAmount);
                    
                    $totalBill += $billAmount;
                    $totalPaid += $paidAmount;
                    $totalBalance += $balanceAmount;
                    
                    $agingClass = '';
                    if ($agingDays > 90) {
                        $agingClass = 'aging-danger';
                    } elseif ($agingDays > 60) {
                        $agingClass = 'aging-warning';
                    } elseif ($agingDays > 30) {
                        $agingClass = 'aging-info';
                    }
                @endphp
                <tr>
                    <td>{{ $sale->customer->customer_name ?? $sale->customer_name ?? '-' }}</td>
                    <td class="text-center">{{ $sale->reference }}</td>
                    <td class="text-center">{{ \Carbon\Carbon::parse($sale->date)->format('d-m-Y') }}</td>
                    <td class="text-right">{{ number_format($billAmount, 2) }}</td>
                    <td class="text-right">{{ number_format($paidAmount, 2) }}</td>
                    <td class="text-right text-danger font-weight-bold">{{ number_format($balanceAmount, 2) }}</td>
                    <td class="text-center {{ $agingClass }}">{{ $agingDays }}</td>
                </tr>
            @empty
                    <tr>
                        <td colspan="8" class="text-center">No Outstanding Bills Found!</td>
                    </tr>
            @endforelse
        </tbody>
        @if($sales->count() > 0)
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right font-weight-bold">Totals:</td>
                    <td class="text-right font-weight-bold">{{ number_format($totalBill, 2) }}</td>
                    <td class="text-right font-weight-bold">{{ number_format($totalPaid, 2) }}</td>
                    <td class="text-right font-weight-bold text-danger">{{ number_format($totalBalance, 2) }}</td>
                        <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div style="margin-top: 15px; font-size: 9px; color: #666; text-align: right;">
        Total Records: {{ $sales->count() }}
    </div>
</body>
</html>
