<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Ledger Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size:12px }
        table { width:100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding:6px; }
        th { background:#f4f4f4 }
        .text-right { text-align:right }
    </style>
</head>
<body>
    <h4>Supplier Ledger Report</h4>
    @if(!empty($filters['start_date']) && !empty($filters['end_date']))
        <div>Period: {{ $filters['start_date'] }} to {{ $filters['end_date'] }}</div>
    @endif

    @foreach($data as $block)
        <h5 style="margin-top:16px">{{ $block['supplier']->supplier_name }}</h5>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Particulars</th>
                    <th>Payment Mode</th>
                    <th class="text-right">Purchased Amount</th>
                    <th class="text-right">Paid Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3"><strong>Opening Balance</strong></td>
                    <td class="text-right">{{ number_format($block['opening'],2) }}</td>
                    <td class="text-right"></td>
                </tr>
                @foreach($block['transactions'] as $t)
                    <tr>
                        <td>{{ !empty($t['date']) ? \Carbon\Carbon::parse($t['date'])->format('d-M-Y') : '' }}</td>
                        <td>{{ ucfirst($t['type']) }} - {{ $t['reference'] }}</td>
                        <td>{{ $t['payment_mode'] ?? '' }}</td>
                        <td class="text-right">{{ $t['credit'] ? number_format($t['credit'],2) : '' }}</td>
                        <td class="text-right">{{ $t['debit'] ? number_format($t['debit'],2) : '' }}</td>
                    </tr>
                @endforeach
                @php $s = $block['summary']; @endphp
                <tr>
                    <td colspan="3" class="text-right"></td>
                    <td class="text-right"><strong>{{ number_format($s['total_credit'],2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($s['total_debit'],2) }}</strong></td>
                </tr>
                @if($s['closing_balance'] > 0)
                <tr>
                    <td colspan="3"><strong>To &nbsp;&nbsp;&nbsp; Closing Balance</strong></td>
                    @if($s['closing_in_debit'])
                        <td class="text-right"></td>
                        <td class="text-right"><strong>{{ number_format($s['closing_balance'],2) }}</strong></td>
                    @else
                        <td class="text-right"><strong>{{ number_format($s['closing_balance'],2) }}</strong></td>
                        <td class="text-right"></td>
                    @endif
                </tr>
                @endif
                <tr>
                    <td colspan="3"></td>
                    <td class="text-right"><strong>{{ number_format($s['balanced_total'],2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($s['balanced_total'],2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>
