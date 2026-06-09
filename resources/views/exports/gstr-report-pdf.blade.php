<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GSTR Report</title>
    <style>
        body          { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; margin: 0; padding: 10px; }
        .header       { text-align: center; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 6px; }
        .header h1    { font-size: 14px; margin: 0 0 4px 0; }
        .header p     { font-size: 8px; margin: 0; }
        .filters      { margin: 5px 0; font-size: 8.5px; }
        /* table-layout:fixed + explicit col widths keep columns within portrait A4 */
        table         { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
        th, td        { border: 1px solid #ccc; padding: 3px 4px; text-align: left;
                        word-wrap: break-word; overflow-wrap: break-word; }
        th            { background-color: #333; color: #fff; font-weight: bold;
                        font-size: 8px; text-align: center; }
        .text-right   { text-align: right; }
        .text-center  { text-align: center; }
        tr.totals td  { background-color: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>GSTR Report</h1>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    @php
        // Active filter labels for the "Filters Applied" line
        $activeFilters = array_filter([
            !empty($filters['hsn'])     ? 'HSN: ' . $filters['hsn']           : null,
            !empty($filters['product']) ? 'Product: ' . $filters['product']   : null,
            ($filters['rate'] ?? '') !== '' ? 'Rate: ' . $filters['rate'] . '%' : null,
            (!empty($filters['start_date']) || !empty($filters['end_date']))
                ? 'Range: ' . ($filters['start_date'] ?? '-') . ' to ' . ($filters['end_date'] ?? '-')
                : null,
        ]);
    @endphp

    @if(count($activeFilters))
        <div class="filters">
            <strong>Filters Applied:</strong> {{ implode(' | ', $activeFilters) }}
        </div>
    @endif

    @php
        // Pre-compute totals for the footer row (single pass)
        $totMrp = 0; $totTaxable = 0; $totIgst = 0; $totCgst = 0; $totSgst = 0; $totQty = 0;
        foreach ($rows as $row) {
            $taxable  = ($row->rate ?? 0) * ($row->quantity ?? 0);
            $lineTax  = round($taxable * (($row->tax_percentage ?? 0) / 100), 2);
            $usesIgst = !empty($row->sale) && ($row->sale->overall_igst ?? 0) > 0;
            $rowCgst  = $usesIgst ? 0 : round($lineTax / 2, 2);
            $rowSgst  = $usesIgst ? 0 : ($lineTax - $rowCgst);   // avoid double-rounding gap
            $totMrp     += ($row->mrp ?? 0) * ($row->quantity ?? 0);
            $totTaxable += $taxable;
            $totIgst    += $usesIgst ? $lineTax : 0;
            $totCgst    += $rowCgst;
            $totSgst    += $rowSgst;
            $totQty     += $row->quantity ?? 0;
        }
    @endphp

    <table>
        {{-- Inline widths on <col> — dompdf does not reliably apply class-based col widths --}}
        <colgroup>
            <col style="width:8%">   {{-- HSN --}}
            <col style="width:21%">  {{-- Description --}}
            <col style="width:5%">   {{-- UQC --}}
            <col style="width:6%">   {{-- Qty --}}
            <col style="width:11%">  {{-- Total Value (MRP) --}}
            <col style="width:11%">  {{-- Taxable Value --}}
            <col style="width:8%">   {{-- IGST --}}
            <col style="width:8%">   {{-- CGST --}}
            <col style="width:8%">   {{-- SGST --}}
            <col style="width:7%">   {{-- Cess --}}
            <col style="width:7%">   {{-- Rate % --}}
        </colgroup>
        <thead>
            <tr>
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
                    $sgst     = $usesIgst ? 0 : ($lineTax - $cgst);  // remainder avoids rounding gap
                    // Display tax percentage: show integer when whole number, otherwise trim trailing zeros
                    $taxPct   = (float)($row->tax_percentage ?? 0);
                    $rateDisplay = (floor($taxPct) == $taxPct) ? (int)$taxPct : rtrim(rtrim((string)$taxPct, '0'), '.');
                    $hsn      = $row->hsn ?? ($row->product->hsn ?? '');
                    $uqc      = $row->product->product_unit ?? '';
                @endphp
                <tr>
                    <td class="text-center">{{ $hsn }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td class="text-center">{{ $uqc }}</td>
                    <td class="text-center">{{ $row->quantity }}</td>
                    <td class="text-right">{{ number_format(($row->mrp ?? 0) * $row->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format($taxable, 2) }}</td>
                    <td class="text-right">{{ number_format($igst, 2) }}</td>
                    <td class="text-right">{{ number_format($cgst, 2) }}</td>
                    <td class="text-right">{{ number_format($sgst, 2) }}</td>
                    <td class="text-right">0.00</td>
                    <td class="text-center">{{ $rateDisplay }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center">No records found</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rows) > 0)
        <tfoot>
            <tr class="totals">
                <td colspan="3" class="text-center"><strong>Total</strong></td>
                <td class="text-center"><strong>{{ $totQty }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totMrp, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totTaxable, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totIgst, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totCgst, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format($totSgst, 2) }}</strong></td>
                <td class="text-right"><strong>0.00</strong></td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
