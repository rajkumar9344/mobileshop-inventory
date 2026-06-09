<table>
    <thead>
        <tr>
            <th>Supplier</th>
            <th>Date</th>
            <th>Particulars</th>
            <th>Payment Mode</th>
            <th>Purchased Amount</th>
            <th>Paid Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($data as $block)
        <tr><td colspan="6"><strong>{{ $block['supplier']->supplier_name }}</strong></td></tr>
        <tr>
            <td></td>
            <td>{{ $filters['start_date'] ?? '' }}</td>
            <td>Opening Balance</td>
            <td></td>
            <td>{{ number_format($block['opening'],2) }}</td>
            <td></td>
        </tr>
        @foreach($block['transactions'] as $t)
            <tr>
                <td></td>
                <td>{{ $t['date'] }}</td>
                <td>{{ ucfirst($t['type']) }} - {{ $t['reference'] }}</td>
                <td>{{ $t['payment_mode'] ?? '' }}</td>
                <td>{{ $t['credit'] ? number_format($t['credit'],2) : '' }}</td>
                <td>{{ $t['debit'] ? number_format($t['debit'],2) : '' }}</td>
            </tr>
        @endforeach
        @php $s = $block['summary']; @endphp
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>{{ number_format($s['total_credit'],2) }}</td>
            <td>{{ number_format($s['total_debit'],2) }}</td>
        </tr>
        @if($s['closing_balance'] > 0)
        <tr>
            <td></td>
            <td></td>
            <td>To Closing Balance</td>
            <td></td>
            @if($s['closing_in_debit'])
                <td></td>
                <td>{{ number_format($s['closing_balance'],2) }}</td>
            @else
                <td>{{ number_format($s['closing_balance'],2) }}</td>
                <td></td>
            @endif
        </tr>
        @endif
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>{{ number_format($s['balanced_total'],2) }}</td>
            <td>{{ number_format($s['balanced_total'],2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
