<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th>Date</th>
            <th>Particulars</th>
            <th>Payment Mode</th>
            <th>Sales Amount</th>
            <th>Received Amount</th>
        </tr>
    </thead>
    <tbody>
    @foreach($data as $block)
        @php $s = $block['summary']; @endphp
        <tr><td colspan="6"><strong>{{ $block['customer']->customer_name }}</strong></td></tr>
        <tr>
            <td></td>
            <td>{{ $filters['start_date'] ?? '' }}</td>
            <td>Opening Balance</td>
            <td></td>
            <td>{{ number_format($s['opening_debit'] ?? ($block['opening'] > 0 ? $block['opening'] : 0),2) }}</td>
            <td>{{ number_format($s['opening_credit'] ?? ($block['opening'] < 0 ? abs($block['opening']) : 0),2) }}</td>
        </tr>
        @foreach($block['transactions'] as $t)
            <tr>
                <td></td>
                <td>{{ $t['date'] }}</td>
                <td>{{ ucfirst($t['type']) }} - {{ $t['reference'] }}</td>
                <td>{{ $t['payment_mode'] ?? '' }}</td>
                <td>{{ $t['debit'] ? number_format($t['debit'],2) : '' }}</td>
                <td>{{ $t['credit'] ? number_format($t['credit'],2) : '' }}</td>
            </tr>
        @endforeach
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td>{{ number_format($s['total_debit'],2) }}</td>
            <td>{{ number_format($s['total_credit'],2) }}</td>
        </tr>
        @if($s['closing_balance'] >= 0)
        <tr>
            <td></td>
            <td></td>
            <td>By Closing Balance</td>
            <td></td>
            @if($s['closing_in_credit'])
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
