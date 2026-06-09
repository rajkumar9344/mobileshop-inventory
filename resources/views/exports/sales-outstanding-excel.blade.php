<table>
    <thead>
        <tr>
            <th>Customer Name</th>
            <th>Sales Bill Ref No</th>
            <th>Bill Date</th>
            <th>Bill Overall Amount</th>
            <th>Received Amount</th>
            <th>Balance Amount</th>
            <th>Bill Due Date</th>
            <th>Aging (Days)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sales as $sale)
            @php
                $agingDays = \App\Exports\SalesOutstandingExport::calculateAging($sale->due_date);
                $billAmount = $sale->overall_net_rate ?? $sale->overall_amount ?? $sale->total_amount ?? 0;
                $paidAmount = $sale->paid_amount ?? 0;
                $balanceAmount = $sale->due_amount ?? ($billAmount - $paidAmount);
            @endphp
            <tr>
                <td>{{ $sale->customer->customer_name ?? $sale->customer_name ?? '-' }}</td>
                <td>{{ $sale->reference }}</td>
                <td>{{ \Carbon\Carbon::parse($sale->date)->format('d-m-Y') }}</td>
                <td>{{ number_format($billAmount, 2) }}</td>
                <td>{{ number_format($paidAmount, 2) }}</td>
                <td>{{ number_format($balanceAmount, 2) }}</td>
                <td>{{ \Carbon\Carbon::parse($sale->due_date)->format('d-m-Y') }}</td>
                <td>{{ $agingDays }}</td>
            </tr>
        @endforeach
    </tbody>
    @if($sales->count() > 0)
        <tfoot>
            <tr>
                <td colspan="3"><strong>Totals:</strong></td>
                <td><strong>{{ number_format($sales->sum(fn($s) => $s->overall_net_rate ?? $s->overall_amount ?? $s->total_amount ?? 0), 2) }}</strong></td>
                <td><strong>{{ number_format($sales->sum('paid_amount'), 2) }}</strong></td>
                <td><strong>{{ number_format($sales->sum('due_amount'), 2) }}</strong></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>
