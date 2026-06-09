<table>
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
        @foreach($rows as $row)
            <tr>
                <td>{{ $row->hsn ?? ($row->product->hsn ?? '') }}</td>
                <td>{{ $row->product_name }}</td>
                <td>{{ $row->product->product_unit ?? '' }}</td>
                <td>{{ $row->quantity }}</td>
                <td>{{ number_format(($row->mrp ?? 0) * $row->quantity, 2) }}</td>
                <td>{{ number_format(($row->rate ?? 0) * $row->quantity, 2) }}</td>
                @php
                    $igst = 0; $cgst = 0; $sgst = 0;
                    $taxable = ($row->rate ?? 0) * ($row->quantity ?? 0);
                    $lineTax = round($taxable * (($row->tax_percentage ?? 0) / 100), 2);
                    if (!empty($row->sale) && ($row->sale->overall_igst ?? 0) > 0) {
                        $igst = $lineTax;
                    } else {
                        $cgst = $sgst = round($lineTax / 2, 2);
                    }
                @endphp
                <td>{{ number_format($igst, 2) }}</td>
                <td>{{ number_format($cgst, 2) }}</td>
                <td>{{ number_format($sgst, 2) }}</td>
                <td>0.00</td>
                <td>{{ rtrim(rtrim((string)($row->tax_percentage ?? 0), '0'), '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
