<div class="invoice-root" style="width:190mm; margin:0 auto; font-family: Arial, Helvetica, sans-serif; font-size:10px; color:#000; box-sizing:border-box;">
    @php
        $is_pdf_request = $is_pdf_request ?? request()->is('quotations/pdf/*');
        $currency = function($amount) use ($is_pdf_request) {
            $formatted = number_format($amount, 2);
            return $is_pdf_request ? $formatted : (format_currency(0, true)[0] . $formatted);
        };
        $vatNo = settings()->company_gst ?? '';
    @endphp

    {{-- ① TITLE --}}
    <div style="text-align:center; padding:12px 0 4px;">
        <div style="font-size:20px; font-weight:800; letter-spacing:1px;">QUOTATION</div>
        @if($vatNo)
            <div style="font-size:11px; font-weight:700; margin-top:5px;">TRN: {{ $vatNo }}</div>
        @endif
    </div>

    {{-- ② HEADER BOX: Bill To (left) | Quotation Date/No + Company (right) --}}
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-size:10px; margin-top:8px;">
        <tr>
            {{-- Left: customer --}}
            <td style="width:48%; vertical-align:top; padding:10px 12px; border-right:1px solid #000;">
                <div style="margin-bottom:5px;">Bill to:</div>
                <div style="font-weight:700; font-size:11px;">{{ $customer->customer_name ?? $quotation->customer_name ?? '' }}</div>
                @if(!empty($customer->address ?? ''))
                    <div style="margin-top:3px;">{{ $customer->address }}</div>
                @endif
                @if(!empty($customer->phone ?? ''))
                    <div style="margin-top:3px;">{{ $customer->phone }}</div>
                @endif
                @if(!empty($customer->vat_id ?? ''))
                    <div style="margin-top:10px;">TRN:{{ $customer->vat_id }}</div>
                @endif
            </td>
            {{-- Right: Quotation Date/No (gray) over Company info --}}
            <td style="width:52%; vertical-align:top; padding:0;">
                <table style="width:100%; border-collapse:collapse; font-size:10px;">
                    <tr>
                        <td style="padding:8px 12px; background-color:#f0f0f0; border-bottom:1px solid #000;">
                            <table style="width:100%; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:2px 0; font-weight:700; width:35%;">Quotation Date</td>
                                    <td style="padding:2px 0; width:8%; text-align:center;">:</td>
                                    <td style="padding:2px 0; font-weight:700;">{{ \Carbon\Carbon::parse($quotation->date)->format('d-m-Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:2px 0; font-weight:700;">Quotation No</td>
                                    <td style="padding:2px 0; text-align:center;">:</td>
                                    <td style="padding:2px 0; font-weight:700;">{{ $quotation->reference }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px; vertical-align:top;">
                            <div style="font-weight:800; font-size:11px;">{{ strtoupper(settings()->company_name) }}</div>
                            @if(settings()->company_address)
                                <div style="margin-top:3px; color:#1a5276;">{{ settings()->company_address }}</div>
                            @endif
                            @if(settings()->company_phone)
                                <div style="margin-top:2px;">Tel No.: {{ settings()->company_phone }}</div>
                            @endif
                            @if(settings()->company_email ?? '')
                                <div style="margin-top:2px; color:#1a5276;">E-mail: {{ settings()->company_email }}</div>
                            @endif
                            @if($vatNo)
                                <div style="margin-top:2px;">VAT No: {{ $vatNo }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ③ PRODUCT TABLE + TOTALS (one grid, like the reference) --}}
    @php
        $i = 1; $total_subtotal = 0; $total_tax = 0; $total_grand = 0; $vat_pct_label = '';
        $details = $quotation->quotationDetails;
        $currencyCode = settings()->currency->code ?? '';
        $codeSuffix   = $currencyCode ? '<br>(' . e($currencyCode) . ')' : '';
        foreach ($details as $item) {
            $vp = (float)($item->tax_percentage ?? 0);
            if ($vat_pct_label === '' && $vp > 0) $vat_pct_label = rtrim(rtrim(number_format($vp, 1), '0'), '.') . '%';
        }

        if (!function_exists('number_to_words')) {
            function number_to_words($number) {
                $whole = floor($number); $fraction = round(($number - $whole) * 100);
                if ($fraction === 100) { $whole += 1; $fraction = 0; }
                $ones = [0=>'zero',1=>'one',2=>'two',3=>'three',4=>'four',5=>'five',6=>'six',7=>'seven',8=>'eight',9=>'nine',10=>'ten',11=>'eleven',12=>'twelve',13=>'thirteen',14=>'fourteen',15=>'fifteen',16=>'sixteen',17=>'seventeen',18=>'eighteen',19=>'nineteen'];
                $tens = [2=>'twenty',3=>'thirty',4=>'forty',5=>'fifty',6=>'sixty',7=>'seventy',8=>'eighty',9=>'ninety'];
                $conv = function($num) use (&$conv, $ones, $tens) {
                    if ($num < 20)       return $ones[$num];
                    if ($num < 100)      { $t=intdiv($num,10); $r=$num%10; return $tens[$t].($r?' '.$ones[$r]:''); }
                    if ($num < 1000)     { $h=intdiv($num,100); $r=$num%100; return $ones[$h].' hundred'.($r?' '.$conv($r):''); }
                    if ($num < 100000)   { $th=intdiv($num,1000); $r=$num%1000; return $conv($th).' thousand'.($r?' '.$conv($r):''); }
                    if ($num < 10000000) { $l=intdiv($num,100000); $r=$num%100000; return $conv($l).' lakh'.($r?' '.$conv($r):''); }
                    return (string)$num;
                };
                $w = ((int)$whole === 0) ? 'zero' : $conv((int)$whole);
                $result = ucwords($w);
                if ($fraction > 0) $result .= ' and ' . ucwords($conv($fraction)) . ' Fils';
                return $result . ' only.';
            }
        }
    @endphp
    <table style="width:100%; border-collapse:collapse; font-size:10px; border:1px solid #000; table-layout:fixed; margin-top:10px;">
        <colgroup>
            <col style="width:24px;">
            <col style="width:*;">
            <col style="width:48px;">
            <col style="width:56px;">
            <col style="width:58px;">
            <col style="width:36px;">
            <col style="width:52px;">
            <col style="width:58px;">
        </colgroup>
        <thead>
        <tr>
            <th style="border:1px solid #000; padding:7px 4px; background-color:#f0f0f0; text-align:center; font-weight:700;">#</th>
            <th style="border:1px solid #000; padding:7px 6px; background-color:#f0f0f0; text-align:left; font-weight:700;">Item Description</th>
            <th style="border:1px solid #000; padding:7px 4px; background-color:#f0f0f0; text-align:center; font-weight:700;">Qty</th>
            <th style="border:1px solid #000; padding:7px 4px; background-color:#f0f0f0; text-align:center; font-weight:700;">Unit Price{!! $codeSuffix !!}</th>
            <th style="border:1px solid #000; padding:7px 4px; background-color:#f0f0f0; text-align:center; font-weight:700;">Amount{!! $codeSuffix !!}</th>
            <th style="border:1px solid #000; padding:7px 2px; background-color:#f0f0f0; text-align:center; font-weight:700;">VAT<br>%</th>
            <th style="border:1px solid #000; padding:7px 4px; background-color:#f0f0f0; text-align:center; font-weight:700;">VAT Amt{!! $codeSuffix !!}</th>
            <th style="border:1px solid #000; padding:7px 4px; background-color:#f0f0f0; text-align:center; font-weight:700;">Total{!! $codeSuffix !!}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($details as $item)
            @php
                $qty        = (float)($item->quantity ?? 0);
                $rate       = (float)($item->rate ?? 0);
                $vat_pct    = (float)($item->tax_percentage ?? 0);
                $vat_amt    = (float)($item->product_tax_amount ?? 0);
                $subtotal   = $qty * $rate;
                $line_total = $subtotal + $vat_amt;
                $total_subtotal += $subtotal;
                $total_tax      += $vat_amt;
                $total_grand    += $line_total;
            @endphp
            <tr>
                <td style="border:1px solid #000; padding:7px 4px; text-align:center;">{{ $i++ }}</td>
                <td style="border:1px solid #000; padding:7px 6px; color:#1a5276; white-space:normal; word-break:break-word;">{{ $item->product_name ?? '' }}</td>
                <td style="border:1px solid #000; padding:7px 4px; text-align:center;">{{ $qty }} {{ $item->unit ?? 'PC' }}</td>
                <td style="border:1px solid #000; padding:7px 4px; text-align:right;">{{ $currency($rate) }}</td>
                <td style="border:1px solid #000; padding:7px 4px; text-align:right;">{{ $currency($subtotal) }}</td>
                <td style="border:1px solid #000; padding:7px 2px; text-align:center;">{{ $vat_pct > 0 ? rtrim(rtrim(number_format($vat_pct, 1), '0'), '.') : '0' }}</td>
                <td style="border:1px solid #000; padding:7px 4px; text-align:right;">{{ $currency($vat_amt) }}</td>
                <td style="border:1px solid #000; padding:7px 4px; text-align:right;">{{ $currency($line_total) }}</td>
            </tr>
        @endforeach

        @php
            $vatLabel = 'VAT ' . ($vat_pct_label ?: '0%');
        @endphp

        {{-- Totals as a continuation of the grid: words cell spans left, amounts fill right columns --}}
        <tr>
            <td colspan="5" rowspan="3" style="border:1px solid #000; padding:7px 6px; vertical-align:top;">
                <div>Total in Words:</div>
                <div style="font-weight:700; margin-top:4px;">{{ trim(($currencyCode ? $currencyCode . ' ' : '') . number_to_words($total_grand)) }}</div>
            </td>
            <td colspan="2" style="border:1px solid #000; padding:6px 4px; text-align:right; font-weight:700;">Total</td>
            <td style="border:1px solid #000; padding:6px 4px; text-align:right; font-weight:700;">{{ $currency($total_subtotal) }}</td>
        </tr>
        <tr>
            <td colspan="2" style="border:1px solid #000; padding:6px 4px; text-align:right; font-weight:700;">{{ $vatLabel }}</td>
            <td style="border:1px solid #000; padding:6px 4px; text-align:right; font-weight:700;">{{ $currency($total_tax) }}</td>
        </tr>
        <tr>
            <td colspan="2" style="border:1px solid #000; padding:6px 4px; text-align:right; font-weight:700;">Grand Total{{ $currencyCode ? ' (' . $currencyCode . ')' : '' }}</td>
            <td style="border:1px solid #000; padding:6px 4px; text-align:right; font-weight:700;">{{ $currency($total_grand) }}</td>
        </tr>
        </tbody>
    </table>

    {{-- ④ DECLARATION — plain text below the table --}}
    <div style="margin-top:12px; font-size:10px;">
        <div style="font-weight:700;">Declaration</div>
        <div style="margin-top:5px;">This quotation is valid for the period stated and the prices shown are subject to confirmation at the time of order.</div>
    </div>

    {{-- ⑤ REMARKS --}}
    <div style="margin-top:12px; font-size:10px;">
        <div style="font-weight:700;">Remarks</div>
        <div style="margin-top:5px;">{{ $quotation->note ? $quotation->note : 'No Remarks' }}</div>
    </div>

    {{-- ⑥ SIGNATURE FOOTER --}}
    <table style="width:100%; border-collapse:collapse; font-size:10px; margin-top:60px;">
        <tr>
            <td style="width:50%; text-align:center; padding:0 25px;">
                <div style="font-weight:700; padding-bottom:25px;">Customer Signature &amp; Stamp</div>
                <div style="border-top:1px solid #000; width:80%; margin:0 auto;">&nbsp;</div>
            </td>
            <td style="width:50%; text-align:center; padding:0 25px;">
                <div style="font-weight:700; padding-bottom:25px;">For {{ settings()->company_name }}</div>
                <div style="border-top:1px solid #000; width:80%; margin:0 auto;">&nbsp;</div>
            </td>
        </tr>
    </table>
</div>
