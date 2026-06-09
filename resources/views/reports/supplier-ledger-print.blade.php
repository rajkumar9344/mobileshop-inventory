<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Ledger Report</title>
    @include('reports.partials._print-styles')
    <style>
        table { table-layout: fixed; }
        colgroup col.col-date  { width: 11%; }
        colgroup col.col-part  { width: 35%; }
        colgroup col.col-mode  { width: 14%; }
        colgroup col.col-purch { width: 15%; }
        colgroup col.col-paid  { width: 15%; }
        thead tr th { background-color: #333; border-color: #555; }
        .supplier-block { margin-bottom: 24px; }
        .supplier-title { background-color: #1a1a2e; color: #fff; padding: 7px 10px; font-size: 12px; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .row-opening td  { background-color: #fffbe6 !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .row-total td    { background-color: #e8e8e8 !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .row-closing td  { background-color: #edf7ed !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .row-balanced td { background-color: #e8e8e8 !important; font-weight: bold; border-top: 2px solid #999; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
        <button class="btn-close2" onclick="window.close()">&#x2715;&nbsp; Close</button>
        <span class="print-tip" style="font-size:11px;color:#888;margin-left:12px;">&#9432; In the print dialog, uncheck <strong>"Headers and footers"</strong> to hide the URL.</span>
    </div>

    <div class="report-header">
        <h2>Supplier Ledger Report</h2>
        <p class="meta">Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>

    @if(!empty($filters['start_date']) || !empty($filters['end_date']))
    <div class="filters-bar">
        <strong>Filters Applied &mdash;</strong>
        <span>Period: <strong>{{ $filters['start_date'] ?? '-' }} to {{ $filters['end_date'] ?? '-' }}</strong></span>
        @if(!empty($filters['supplier_id']))
            <span>Supplier: <strong>{{ $filters['supplier_name'] ?? $filters['supplier_id'] }}</strong></span>
        @endif
    </div>
    @endif

    @foreach($data as $block)
    <div class="supplier-block">
        <div class="supplier-title">{{ $block['supplier']->supplier_name }}</div>
        <table>
            <colgroup>
                <col class="col-date">
                <col class="col-part">
                <col class="col-mode">
                <col class="col-purch">
                <col class="col-paid">
            </colgroup>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Particulars</th>
                    <th>Payment Mode</th>
                    <th>Purchased Amount</th>
                    <th>Paid Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="row-opening">
                    <td colspan="3"><strong>Opening Balance</strong></td>
                    <td class="t-right">{{ number_format($block['opening'], 2) }}</td>
                    <td class="t-right"></td>
                </tr>
                @foreach($block['transactions'] as $t)
                <tr>
                    <td class="t-center">{{ !empty($t['date']) ? \Carbon\Carbon::parse($t['date'])->format('d-m-Y') : '' }}</td>
                    <td>{{ ucfirst($t['type']) }} - {{ $t['reference'] }}</td>
                    <td class="t-center">{{ $t['payment_mode'] ?? '' }}</td>
                    <td class="t-right">{{ $t['credit'] ? number_format($t['credit'], 2) : '' }}</td>
                    <td class="t-right">{{ $t['debit'] ? number_format($t['debit'], 2) : '' }}</td>
                </tr>
                @endforeach
                @php $s = $block['summary']; @endphp
                <tr class="row-total">
                    <td colspan="3" class="t-right"></td>
                    <td class="t-right"><strong>{{ number_format($s['total_credit'], 2) }}</strong></td>
                    <td class="t-right"><strong>{{ number_format($s['total_debit'], 2) }}</strong></td>
                </tr>
                @if($s['closing_balance'] > 0)
                <tr class="row-closing">
                    <td colspan="3"><strong>To &nbsp;&nbsp; Closing Balance</strong></td>
                    @if($s['closing_in_debit'])
                        <td class="t-right"></td>
                        <td class="t-right"><strong>{{ number_format($s['closing_balance'], 2) }}</strong></td>
                    @else
                        <td class="t-right"><strong>{{ number_format($s['closing_balance'], 2) }}</strong></td>
                        <td class="t-right"></td>
                    @endif
                </tr>
                @endif
                <tr class="row-balanced">
                    <td colspan="3"></td>
                    <td class="t-right"><strong>{{ number_format($s['balanced_total'], 2) }}</strong></td>
                    <td class="t-right"><strong>{{ number_format($s['balanced_total'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach

    @if(empty($data))
    <p style="text-align:center;padding:30px;color:#888;">No ledger data found for the selected filters.</p>
    @endif

    <div class="report-footer">
        Supplier Ledger Report &bull; Printed on {{ date('d-m-Y H:i:s') }}
    </div>

</body>
</html>
