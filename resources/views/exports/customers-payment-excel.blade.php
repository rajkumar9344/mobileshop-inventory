<table>
    <thead>
        <tr>
            <th>Customer Name</th>
            <th>Sales Bill Ref No</th>
            <th>Sales Bill Date</th>
            <th>Sales Bill Overall Amount</th>
            <th>Received Amount</th>
            <th>Received Date</th>
            <th>Payment Mode</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $line)
            <tr>
                <td>{{ $line->receipt->customer->customer_name ?? '-' }}</td>
                <td>{{ $line->sale->reference ?? $line->bill_ref ?? '-' }}</td>
                <td>{{ optional($line->sale->date ? \Carbon\Carbon::parse($line->sale->date) : null)->format('d-m-Y') ?? (optional($line->bill_date)->format('d-m-Y') ?? '-') }}</td>
                <td class="text-right">{{ number_format($line->bill_amount ?? ($line->sale->overall_net_rate ?? $line->sale->overall_amount ?? $line->sale->total_amount ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format($line->payment_amount, 2) }}</td>
                <td>{{ optional($line->receipt->date ? \Carbon\Carbon::parse($line->receipt->date) : null)->format('d-m-Y') }}</td>
                <td>{{ $line->receipt->payment_mode ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
