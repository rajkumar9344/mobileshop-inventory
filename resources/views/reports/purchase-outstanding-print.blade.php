<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Outstanding Report</title>
    @include('reports.partials._print-styles')
    <style>
        table { table-layout: fixed; }
        colgroup col.col-no    { width: 4%; }
        colgroup col.col-supp  { width: 18%; }
        colgroup col.col-ref   { width: 14%; }
        colgroup col.col-bdate { width: 9%; }
        colgroup col.col-bamt  { width: 11%; }
        colgroup col.col-paid  { width: 11%; }
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
        <span class="record-count">Total Records: <strong>{{ $purchases->count() }}</strong></span>
    </div>

    <div class="report-header">
        <h2>Purchase Bills Outstanding Report</h2>
        <p class="meta">Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    @if(!empty($filters['supplier_name']) || !empty($filters['reference']) || !empty($filters['aging_range']))
    <div class="filters-bar">
        <strong>Filters Applied &mdash;</strong>
        @if(!empty($filters['supplier_name']))
            <span>Supplier: <strong>{{ $filters['supplier_name'] }}</strong></span>
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
            <col class="col-supp">
            <col class="col-ref">
            <col class="col-bdate">
            <col class="col-bamt">
            <col class="col-paid">
            <col class="col-bal">
            <col class="col-due">
            <col class="col-aging">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Supplier Name</th>
                <th>Purchase Bill Ref No</th>
                <th>Bill Date</th>
                <th>Bill Overall Amount</th>
                <th>Paid Amount</th>
                <th>Balance Amount</th>
                <th>Aging (Days)</th>
            </tr>
        </thead>
        <tbody>
            @php $totalBill = 0; $totalPaid = 0; $totalBalance = 0; @endphp
            @forelse($purchases as $purchase)
            @php
                $billAmount    = $purchase->overall_net_rate ?? $purchase->total_amount ?? 0;
                $paidAmount    = $purchase->paid_amount ?? 0;
                $balanceAmount = $purchase->due_amount ?? ($billAmount - $paidAmount);
                $agingDays     = \Carbon\Carbon::parse($purchase->date)->diffInDays(\Carbon\Carbon::today());
                $totalBill    += $billAmount;
                $totalPaid    += $paidAmount;
                $totalBalance += $balanceAmount;
            @endphp
            <tr>
                <td class="t-center">{{ $loop->iteration }}</td>
                <td class="t-left">{{ $purchase->supplier->supplier_name ?? $purchase->supplier_name ?? '-' }}</td>
                <td class="t-center">{{ $purchase->reference }}</td>
                <td class="t-center">{{ \Carbon\Carbon::parse($purchase->date)->format('d-m-Y') }}</td>
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
            @if($purchases->count() > 0)
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
        Purchase Bills Outstanding Report &bull; Printed on {{ now()->format('d-m-Y h:i:s A') }} &bull; Total {{ $purchases->count() }} record(s)
    </div>

</body>
</html>
