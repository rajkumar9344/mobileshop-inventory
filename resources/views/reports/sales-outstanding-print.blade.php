<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Outstanding Report</title>
    @include('reports.partials._print-styles')
    <style>
        table { table-layout: fixed; }
        colgroup col.col-no    { width: 4%; }
        colgroup col.col-cust  { width: 18%; }
        colgroup col.col-ref   { width: 14%; }
        colgroup col.col-bdate { width: 9%; }
        colgroup col.col-bamt  { width: 11%; }
        colgroup col.col-rcvd  { width: 11%; }
        colgroup col.col-bal   { width: 11%; }
        colgroup col.col-due   { width: 9%; }
        colgroup col.col-aging { width: 13%; }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
        <button class="btn-close2" onclick="window.close()">&#x2715;&nbsp; Close</button>
        <span class="print-tip" style="font-size:11px;color:#888;margin-left:12px;">&#9432; In the print dialog, uncheck <strong>"Headers and footers"</strong> to hide the URL.</span>
        <span class="record-count">Total Records: <strong>{{ $sales->count() }}</strong></span>
    </div>

    <div class="report-header">
        <h2>Sales Bills Outstanding Report</h2>
        <p class="meta">Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>

    @if(!empty($filters['customer_name']) || !empty($filters['reference']) || !empty($filters['aging_range']))
    <div class="filters-bar">
        <strong>Filters Applied &mdash;</strong>
        @if(!empty($filters['customer_name']))
            <span>Customer: <strong>{{ $filters['customer_name'] }}</strong></span>
        @endif
        @if(!empty($filters['reference']))
            <span>Reference: <strong>{{ $filters['reference'] }}</strong></span>
        @endif
        @if(!empty($filters['aging_range']))
            <span>Aging: <strong>{{ $filters['aging_range'] }} days</strong></span>
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
            <col class="col-rcvd">
            <col class="col-bal">
            <col class="col-due">
            <col class="col-aging">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
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
                $billAmount    = $sale->overall_net_rate ?? $sale->overall_amount ?? $sale->total_amount ?? 0;
                $paidAmount    = $sale->paid_amount ?? 0;
                $balanceAmount = $sale->due_amount ?? ($billAmount - $paidAmount);
                $agingDays     = \Carbon\Carbon::parse($sale->date)->diffInDays(\Carbon\Carbon::today());
                $totalBill    += $billAmount;
                $totalPaid    += $paidAmount;
                $totalBalance += $balanceAmount;
            @endphp
            <tr>
                <td class="t-center">{{ $loop->iteration }}</td>
                <td class="t-left">{{ $sale->customer->customer_name ?? $sale->customer_name ?? '-' }}</td>
                <td class="t-center">{{ $sale->reference }}</td>
                <td class="t-center">{{ \Carbon\Carbon::parse($sale->date)->format('d-m-Y') }}</td>
                <td class="t-right">{{ number_format($billAmount, 2) }}</td>
                <td class="t-right">{{ number_format($paidAmount, 2) }}</td>
                <td class="t-right text-danger">{{ number_format($balanceAmount, 2) }}</td>
                <td class="t-center {{ $agingDays > 90 ? 'text-danger' : '' }}">{{ $agingDays }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="t-center" style="padding:20px;color:#888;">No outstanding bills found.</td>
            </tr>
            @endforelse
            @if($sales->count() > 0)
            <tr style="background-color:#e8e8e8;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                <td colspan="4" class="t-right" style="border:1px solid #999;">Totals:</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totalBill, 2) }}</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totalPaid, 2) }}</td>
                <td class="t-right text-danger" style="border:1px solid #999;">{{ number_format($totalBalance, 2) }}</td>
                <td colspan="1" style="border:1px solid #999;"></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="report-footer">
        Sales Bills Outstanding Report &bull; Printed on {{ date('d-m-Y H:i:s') }} &bull; Total {{ $sales->count() }} record(s)
    </div>

</body>
</html>
