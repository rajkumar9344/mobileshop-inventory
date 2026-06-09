<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GSTR Report</title>
    @include('reports.partials._print-styles')
    <style>
        body        { font-size: 11px; }
        table       { table-layout: fixed; }
        thead tr th { font-size: 9px; padding: 6px 4px; }
        tbody tr td { font-size: 10px; padding: 4px; }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
        <button class="btn-close2" onclick="window.close()">&#x2715;&nbsp; Close</button>
        <span class="print-tip" style="font-size:11px;color:#888;margin-left:12px;">&#9432; In the print dialog, uncheck <strong>"Headers and footers"</strong> to hide the URL.</span>
        <span class="record-count">Total Records: <strong>{{ $rows->count() }}</strong></span>
    </div>

    <div class="report-header">
        <h2>GSTR Report</h2>
        <p class="meta">Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>

    @php
        $hasFilters = !empty($filters['hsn']) || !empty($filters['product']) || ($filters['rate'] ?? '') !== '' || !empty($filters['start_date']) || !empty($filters['end_date']);
    @endphp
    @if($hasFilters)
    <div class="filters-bar">
        <strong>Filters Applied &mdash;</strong>
        @if(!empty($filters['hsn']))
            <span>HSN: <strong>{{ $filters['hsn'] }}</strong></span>
        @endif
        @if(!empty($filters['product']))
            <span>Product: <strong>{{ $filters['product'] }}</strong></span>
        @endif
        @if(($filters['rate'] ?? '') !== '')
            <span>Rate: <strong>{{ $filters['rate'] }}%</strong></span>
        @endif
        @if(!empty($filters['start_date']) || !empty($filters['end_date']))
            <span>Period: <strong>{{ $filters['start_date'] ?? '-' }} to {{ $filters['end_date'] ?? '-' }}</strong></span>
        @endif
    </div>
    @endif

    @php
        $totMrp = 0; $totTaxable = 0; $totIgst = 0; $totCgst = 0; $totSgst = 0; $totQty = 0;
        foreach ($rows as $row) {
            $taxable  = ($row->rate ?? 0) * ($row->quantity ?? 0);
            $lineTax  = round($taxable * (($row->tax_percentage ?? 0) / 100), 2);
            $usesIgst = !empty($row->sale) && ($row->sale->overall_igst ?? 0) > 0;
            $rowCgst  = $usesIgst ? 0 : round($lineTax / 2, 2);
            $rowSgst  = $usesIgst ? 0 : ($lineTax - $rowCgst);
            $totMrp     += ($row->mrp ?? 0) * ($row->quantity ?? 0);
            $totTaxable += $taxable;
            $totIgst    += $usesIgst ? $lineTax : 0;
            $totCgst    += $rowCgst;
            $totSgst    += $rowSgst;
            $totQty     += $row->quantity ?? 0;
        }
    @endphp

    <table>
        <colgroup>
            <col style="width:4%">   {{-- # --}}
            <col style="width:7%">   {{-- HSN --}}
            <col style="width:19%">  {{-- Description --}}
            <col style="width:5%">   {{-- UQC --}}
            <col style="width:6%">   {{-- Qty --}}
            <col style="width:10%">  {{-- Total Value (MRP) --}}
            <col style="width:10%">  {{-- Taxable Value --}}
            <col style="width:8%">   {{-- IGST --}}
            <col style="width:8%">   {{-- CGST --}}
            <col style="width:8%">   {{-- SGST --}}
            <col style="width:7%">   {{-- Cess --}}
            <col style="width:8%">   {{-- Rate % --}}
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>HSN</th>
                <th>Description</th>
                <th>UQC</th>
                <th>Quantity</th>
                <th>Total Value (MRP)</th>
                <th>Taxable Value</th>
                <th>IGST</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>Cess</th>
                <th>Rate %</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            @php
                $taxable  = ($row->rate ?? 0) * ($row->quantity ?? 0);
                $lineTax  = round($taxable * (($row->tax_percentage ?? 0) / 100), 2);
                $usesIgst = !empty($row->sale) && ($row->sale->overall_igst ?? 0) > 0;
                $igst     = $usesIgst ? $lineTax : 0;
                $cgst     = $usesIgst ? 0 : round($lineTax / 2, 2);
                $sgst     = $usesIgst ? 0 : ($lineTax - $cgst);
                $taxPct   = (float)($row->tax_percentage ?? 0);
                $rateDisp = (floor($taxPct) == $taxPct) ? (int)$taxPct : rtrim(rtrim((string)$taxPct, '0'), '.');
                $hsn      = $row->hsn ?? ($row->product->hsn ?? '');
                $uqc      = $row->product->product_unit ?? '';
            @endphp
            <tr>
                <td class="t-center">{{ $loop->iteration }}</td>
                <td class="t-center">{{ $hsn }}</td>
                <td class="t-left">{{ $row->product_name }}</td>
                <td class="t-center">{{ $uqc }}</td>
                <td class="t-center">{{ $row->quantity }}</td>
                <td class="t-right">{{ number_format(($row->mrp ?? 0) * $row->quantity, 2) }}</td>
                <td class="t-right">{{ number_format($taxable, 2) }}</td>
                <td class="t-right">{{ number_format($igst, 2) }}</td>
                <td class="t-right">{{ number_format($cgst, 2) }}</td>
                <td class="t-right">{{ number_format($sgst, 2) }}</td>
                <td class="t-right">0.00</td>
                <td class="t-center">{{ $rateDisp }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="t-center" style="padding:20px;color:#888;">No records found.</td>
            </tr>
            @endforelse
            @if($rows->count() > 0)
            <tr style="background-color:#e8e8e8;font-weight:bold;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                <td colspan="4" class="t-center" style="border:1px solid #999;">Total</td>
                <td class="t-center" style="border:1px solid #999;">{{ $totQty }}</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totMrp, 2) }}</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totTaxable, 2) }}</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totIgst, 2) }}</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totCgst, 2) }}</td>
                <td class="t-right" style="border:1px solid #999;">{{ number_format($totSgst, 2) }}</td>
                <td class="t-right" style="border:1px solid #999;">0.00</td>
                <td style="border:1px solid #999;"></td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="report-footer">
        GSTR Report &bull; Printed on {{ date('d-m-Y H:i:s') }} &bull; Total {{ $rows->count() }} record(s)
    </div>

</body>
</html>
