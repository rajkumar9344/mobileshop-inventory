<table>
    <thead>
        <tr>
            <th>Supplier Name</th>
            <th>Purchase Bill Ref No</th>
            <th>Bill Date</th>
            <th>Bill Overall Amount</th>
            <th>Paid Amount</th>
            <th>Balance Amount</th>
            <th>Bill Due Date</th>
            <th>Aging (Days)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($purchases as $purchase)
            @php
                $agingDays = \App\Exports\PurchaseOutstandingExport::calculateAging($purchase->due_date);
                $billAmount = $purchase->overall_net_rate ?? $purchase->total_amount ?? 0;
                $paidAmount = $purchase->paid_amount ?? 0;
                $balanceAmount = $purchase->due_amount ?? ($billAmount - $paidAmount);
            @endphp
            <tr>
                <td>{{ $purchase->supplier->supplier_name ?? $purchase->supplier_name ?? '-' }}</td>
                <td>{{ $purchase->reference }}</td>
                <td>{{ \Carbon\Carbon::parse($purchase->date)->format('d-m-Y') }}</td>
                <td>{{ number_format($billAmount, 2) }}</td>
                <td>{{ number_format($paidAmount, 2) }}</td>
                <td>{{ number_format($balanceAmount, 2) }}</td>
                <td>{{ \Carbon\Carbon::parse($purchase->due_date)->format('d-m-Y') }}</td>
                <td>{{ $agingDays }}</td>
            </tr>
        @endforeach
    </tbody>
    @if($purchases->count() > 0)
        <tfoot>
            <tr>
                <td colspan="3"><strong>Totals:</strong></td>
                <td><strong>{{ number_format($purchases->sum(fn($p) => $p->overall_net_rate ?? $p->total_amount ?? 0), 2) }}</strong></td>
                <td><strong>{{ number_format($purchases->sum('paid_amount'), 2) }}</strong></td>
                <td><strong>{{ number_format($purchases->sum('due_amount'), 2) }}</strong></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>
