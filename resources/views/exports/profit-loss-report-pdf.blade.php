<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profit / Loss Report - {{ now()->format('d-m-Y') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .header h1 { margin: 0; color: #333; font-size: 18px; }
        .filters { margin-bottom: 12px; background-color: #f8f9fa; padding: 8px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background-color: #f0f0f0; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .profit { color: #1e7e34; font-weight: bold; }
        .loss { color: #c0392b; font-weight: bold; }
        .no-data { text-align: center; font-style: italic; color: #666; }
        tfoot td { font-weight: bold; background-color: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Profit / Loss Report (per Sales Bill)</h1>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    @php
        $hasFilters = !empty($filters['customer_name']) || !empty($filters['reference']) || !empty($filters['start_date']) || !empty($filters['end_date']) || !empty($filters['pl_status']);
    @endphp
    @if($hasFilters)
        <div class="filters">
            <strong>Filters:</strong>
            @if(!empty($filters['customer_name'])) Customer: {{ $filters['customer_name'] }} | @endif
            @if(!empty($filters['reference'])) Bill Ref: {{ $filters['reference'] }} | @endif
            @if(!empty($filters['start_date'])) From: {{ \Carbon\Carbon::parse($filters['start_date'])->format('d-m-Y') }} | @endif
            @if(!empty($filters['end_date'])) To: {{ \Carbon\Carbon::parse($filters['end_date'])->format('d-m-Y') }} | @endif
            @if(!empty($filters['pl_status'])) Status: {{ ucfirst($filters['pl_status']) }} @endif
        </div>
    @endif

    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Customer Name</th>
            <th>Bill Ref No</th>
            <th>Bill Date</th>
            <th class="text-right">Amount (Incl. VAT)</th>
            <th class="text-right">Purchased Rate Total (Incl. VAT)</th>
            <th class="text-right">Profit / Loss</th>
            <th class="text-center">Status</th>
        </tr>
        </thead>
        <tbody>
        @php
            $t_incl = 0; $t_purchase = 0; $t_profit = 0;
        @endphp
        @forelse($sales as $i => $sale)
            @php
                // Computed columns are in paise
                $incl     = $sale->amount_incl_vat / 100;
                $purchase = $sale->purchase_total / 100;
                $profit   = $sale->profit_amount / 100;
                $t_incl += $incl; $t_purchase += $purchase; $t_profit += $profit;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $sale->customer->customer_name ?? $sale->customer_name ?? '-' }}</td>
                <td>{{ $sale->reference }}</td>
                <td>{{ \Carbon\Carbon::parse($sale->date)->format('d-m-Y') }}</td>
                <td class="text-right">{{ number_format($incl, 2) }}</td>
                <td class="text-right">{{ number_format($purchase, 2) }}</td>
                <td class="text-right {{ $profit >= 0 ? 'profit' : 'loss' }}">{{ number_format(abs($profit), 2) }}</td>
                <td class="text-center {{ $profit >= 0 ? 'profit' : 'loss' }}">{{ $profit >= 0 ? 'Profit' : 'Loss' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="no-data">No sales bills found for the selected filters.</td></tr>
        @endforelse
        </tbody>
        @if(count($sales))
        <tfoot>
        <tr>
            <td colspan="4" class="text-right">Total</td>
            <td class="text-right">{{ number_format($t_incl, 2) }}</td>
            <td class="text-right">{{ number_format($t_purchase, 2) }}</td>
            <td class="text-right {{ $t_profit >= 0 ? 'profit' : 'loss' }}">{{ number_format(abs($t_profit), 2) }} {{ $t_profit >= 0 ? '(Profit)' : '(Loss)' }}</td>
            <td></td>
        </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
