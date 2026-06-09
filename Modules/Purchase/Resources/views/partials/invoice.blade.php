<div class="invoice-root" style="width:180mm; margin:0 auto; font-family: Arial, Helvetica, sans-serif; font-size:10px; color:#000; border:1px solid #000; box-sizing:border-box; position:relative; height:250mm;">
    @php

        $is_pdf_request = $is_pdf_request ?? request()->is('purchases/pdf/*');
        $currency = function($amount) use ($is_pdf_request) {
            $formatted = number_format($amount, 2);
            return $is_pdf_request ? $formatted : ('₹' . $formatted);
        };
    @endphp
    {{-- HEADER SECTION --}}
    <div class="invoice-header" style="padding:8px 0;">
        {{-- top header row with GST no (left), company (center), phone (right) --}}
        <table style="width:100%; border-collapse:collapse; margin-bottom:6px;">
            <tr>
                <td style="width:30%; vertical-align:top; font-size:10px; padding-left:6px;">
                    <div style="font-weight:700;">GST NO: {{ settings()->company_gst ?? '' }}</div>
                </td>
                <td style="width:40%; vertical-align:top; text-align:center;">
                    <div style="font-weight:800; font-size:16px;">{{ strtoupper(settings()->company_name) }}</div>
                    <div style="font-size:10px; margin-top:3px;">{{ settings()->company_address }}</div>
                    <div style="font-size:10px; margin-top:2px;">{{ settings()->company_email ?? '' }}</div>
                </td>
                <td style="width:30%; vertical-align:top; text-align:right; font-size:10px; padding-right:6px;">
                    <div style="font-weight:700;">PH: {{ settings()->company_phone }}</div>
                </td>
            </tr>
        </table>

        {{-- invoice metadata row (boxed cells) --}}
        <table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10px;">
            <tr>
                <td style="border:1px solid #000; padding:6px; width:30%; border-left:0;">Invoice No: <strong>{{ $purchase->invoice_no }}</strong></td>
                <td style="border:1px solid #000; padding:6px; width:20%;">Bill Type: {{ $purchase->bill_type ?? 'CASH' }}</td>
                <td style="border:1px solid #000; padding:6px; width:15%;">Page: 1 of 1</td>
                <td style="border:1px solid #000; padding:6px; width:20%;">Invoice Date: {{ \Carbon\Carbon::parse($purchase->date)->format('d/m/Y') }}</td>
                <td style="border:1px solid #000; padding:6px; width:15%; border-right:0;">{{ \Carbon\Carbon::parse($purchase->created_at ?? $purchase->date)->format('h:i:s A') }}</td>
            </tr>
        </table>

        {{-- supplier & vehicle row (left supplier, right vehicle fields) --}}
        <table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10px;">
            <tr>
                <td style="width:60%; vertical-align:top; padding-left:6px;">
                    <div style="font-weight:700;">{{ optional($supplier)->supplier_name }}</div>
                    <div style="font-size:10px;">{{ optional($supplier)->address }}</div>
                    <div style="font-size:10px;">GST NO: {{ optional($supplier)->gst_no }}</div>
                </td>
                <td style="width:40%; vertical-align:top;">
                    <div style="font-size:10px;">Vehicle Name:</div>
                    <div style="font-size:10px;">Vehicle No:</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- BODY SECTION --}}
    <!-- reserve space for footer -->
    <div class="invoice-body" style="padding:0; margin-bottom:20mm;">
        {{-- items table with GST column --}}
        <table style="width:100%; border-collapse:collapse; margin-top:8px; font-size:10px; border-top:1px solid #000; border-bottom:1px solid #000; table-layout:fixed;">
            <colgroup>
                <col style="width:30px;">
                <col style="width:70px;">
                <col style="width:100px;">
                <col style="width:70px;">
                <col style="width:40px;">
                <col style="width:70px;">
                <col style="width:70px;">
                <col style="width:50px;">
                <col style="width:50px;">
                <col style="width:50px;">
                <col style="width:80px;">
            </colgroup>
            <thead>
            <tr>
                <th style="border:1px solid #000; padding:6px; width:30px; border-left:0;">No</th>
                <th style="border:1px solid #000; padding:6px; width:70px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">Product Code</th>
                <th style="border:1px solid #000; padding:6px; width:100px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">Product Name</th>
                <th style="border:1px solid #000; padding:6px; width:70px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">HSN</th>
                <th style="border:1px solid #000; padding:6px; width:40px;">Qty</th>
                <th style="border:1px solid #000; padding:6px; width:70px;">MRP</th>
                <th style="border:1px solid #000; padding:6px; width:70px;">Rate</th>
                <th style="border:1px solid #000; padding:6px; width:50px;">SGST</th>
                <th style="border:1px solid #000; padding:6px; width:50px;">CGST</th>
                <th style="border:1px solid #000; padding:6px; width:50px;">GST</th>
                <th style="border:1px solid #000; padding:6px; width:80px; border-right:0;">Value</th>
            </tr>
            </thead>
            <tbody>
            @php $i=1; $total_cgst=0; $total_sgst=0; $total_gst=0; $total_value=0; $total_qty = 0; @endphp
            @foreach($purchase->purchaseDetails as $item)
                @php
                    $qty = (float) ($item->quantity ?? 0);
                    $mrp = (float) ($item->mrp ?? 0);
                    $rate = (float) ($item->rate ?? 0);
                    $value = $qty * $rate;
                    $gst = (float) ($item->product_tax_amount ?? 0);
                    $sgst = $cgst = $gst / 2;
                    $total_cgst += $cgst;
                    $total_sgst += $sgst;
                    $total_gst += $gst;
                    $total_value += $value;
                    $total_qty += $qty;
                @endphp
                <tr>
                    <td style="border:1px solid #000; padding:6px; text-align:center; border-left:0;">{{ $i++ }}</td>
                    <td style="border:1px solid #000; padding:6px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $item->productCode->code ?? $item->product_code ?? '' }}</td>
                    <td style="border:1px solid #000; padding:6px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $item->product_name ?? '' }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:center; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $item->hsn ?? '' }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:center;">{{ $qty }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $currency($mrp) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $currency($rate) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $currency($sgst) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $currency($cgst) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right;">{{ $currency($gst) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right; border-right:0;">{{ $currency($value) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Totals row aligned under the same columns as the items table --}}
        <table style="width:100%; border-collapse:collapse; margin-top:2px; font-size:10px; table-layout:fixed;">
            <colgroup>
                <col style="width:30px;">
                <col style="width:70px;">
                <col style="width:100px;">
                <col style="width:70px;">
                <col style="width:40px;">
                <col style="width:70px;">
                <col style="width:70px;">
                <col style="width:50px;">
                <col style="width:50px;">
                <col style="width:50px;">
                <col style="width:80px;">
            </colgroup>
            <tr>
                <td style="padding:4px;"></td>
                <td style="padding:4px;"></td>
                <td style="padding:4px;"></td>
                <td style="padding:4px;"></td>
                <td style="padding:4px; text-align:center; font-weight:700;">{{ $total_qty }}</td>
                <td style="padding:4px;"></td>
                <td style="padding:4px;"></td>
                <td style="padding:4px; text-align:right;">{{ $currency($total_sgst) }}</td>
                <td style="padding:4px; text-align:right;">{{ $currency($total_cgst) }}</td>
                <td style="padding:4px; text-align:right;">{{ $currency($total_gst) }}</td>
                <td style="padding:4px; text-align:right; font-weight:700;">{{ $currency($total_value) }}</td>
            </tr>
        </table>

        {{-- Rupees in words on the left and Round Off / Total aligned under the Value column on the right --}}
        @php
            if (!function_exists('number_to_words')) {
                function number_to_words($number)
                {
                // split rupees and paise, rounding paise to nearest integer
                $whole = floor($number);
                $fraction = round(($number - $whole) * 100);
                if ($fraction === 100) {
                    $whole += 1;
                    $fraction = 0;
                }

                $n = (int) $whole;
                $ones = [0=>'zero',1=>'one',2=>'two',3=>'three',4=>'four',5=>'five',6=>'six',7=>'seven',8=>'eight',9=>'nine',10=>'ten',11=>'eleven',12=>'twelve',13=>'thirteen',14=>'fourteen',15=>'fifteen',16=>'sixteen',17=>'seventeen',18=>'eighteen',19=>'nineteen'];
                $tens = [2=>'twenty',3=>'thirty',4=>'forty',5=>'fifty',6=>'sixty',7=>'seventy',8=>'eighty',9=>'ninety'];
                $convert = function($num) use (&$convert, $ones, $tens) {
                    if ($num < 20) return $ones[$num];
                    if ($num < 100) {
                        $t = intdiv($num, 10);
                        $r = $num % 10;
                        return $tens[$t] . ($r ? ' ' . $ones[$r] : '');
                    }
                    if ($num < 1000) {
                        $h = intdiv($num, 100);
                        $r = $num % 100;
                        return $ones[$h] . ' hundred' . ($r ? ' ' . $convert($r) : '');
                    }
                    if ($num < 100000) {
                        $th = intdiv($num, 1000);
                        $r = $num % 1000;
                        return $convert($th) . ' thousand' . ($r ? ' ' . $convert($r) : '');
                    }
                    if ($num < 10000000) {
                        $l = intdiv($num, 100000);
                        $r = $num % 100000;
                        return $convert($l) . ' lakh' . ($r ? ' ' . $convert($r) : '');
                    }
                    if ($num < 1000000000) {
                        $m = intdiv($num, 1000000);
                        $r = $num % 1000000;
                        return $convert($m) . ' million' . ($r ? ' ' . $convert($r) : '');
                    }
                    return (string)$num;
                };
                $words = $convert($n);
                $result = 'Rupees ' . strtoupper($words);
                if ($fraction > 0) {
                    $wordsFrac = $convert($fraction);
                    $result .= ' AND ' . strtoupper($wordsFrac) . ' PAISA';
                }
                $result .= ' ONLY';
                return $result;
                }
            }
            $rawGrand = $total_value + $total_gst;
            $computedGrand = round($rawGrand, 2);
            $roundOff = $computedGrand - $rawGrand;
            $finalDiscount = ($purchase->discount_amount ?? 0) / 100;
            $displayGrand = $purchase->total_amount ?? $computedGrand;
        @endphp

        {{-- Rupees in words and totals section using table layout for PDF compatibility --}}
        <table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10px;">
            <tr>
                <td style="width:60%; vertical-align:top; padding-left:6px;">
                    <div style="font-weight:700;">{{ number_to_words($total_value + $total_gst + ($purchase->overall_other ?? 0)) }}</div>
                </td>
                <td style="width:40%; vertical-align:top; padding-right:6px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:4px; text-align:right; font-weight:700;">GST</td>
                            <td style="padding:4px; text-align:right; width:80px;">{{ $currency($total_gst) }}</td>
                        </tr>
                        @if(($purchase->overall_other ?? 0) != 0)
                        <tr>
                            <td style="padding:4px; text-align:right; font-weight:700;">Other</td>
                            <td style="padding:4px; text-align:right;">{{ $currency($purchase->overall_other) }}</td>
                        </tr>
                        @endif
                        @if($finalDiscount > 0)
                        <tr>
                            <td style="padding:4px; text-align:right; font-weight:700;">Discount</td>
                            <td style="padding:4px; text-align:right;">-{{ $currency($finalDiscount) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="padding:4px; text-align:right; font-weight:700; border-top:1px solid #000;">Total</td>
                            <td style="padding:4px; text-align:right; font-weight:700; border-top:1px solid #000;">{{ $currency($total_value + $total_gst + ($purchase->overall_other ?? 0)) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- FOOTER SECTION - positioned at bottom but inside border --}}
    <div class="invoice-footer" style="position:absolute; bottom:8px; left:0; right:0; box-sizing:border-box; height:30mm;">
            <table style="width:100%; border-collapse:collapse; font-size:10px;">
                <tr>
                    <td style="border:1px solid #000; padding:6px; width:20%; border-left:0;">Bank:</td>
                    <td style="border:1px solid #000; padding:6px; width:30%;">{{ settings()->bank_name ?? '' }}</td>
                    <td style="border:1px solid #000; padding:6px; width:15%;">A/c No:</td>
                    <td style="border:1px solid #000; padding:6px; width:35%; border-right:0;">{{ settings()->bank_account ?? '' }}</td>
                </tr>
                <tr>
                    <td style="border:1px solid #000; padding:6px; border-left:0;">Branch:</td>
                    <td style="border:1px solid #000; padding:6px;">{{ settings()->bank_branch ?? '' }}</td>
                    <td style="border:1px solid #000; padding:6px;">IFSC Code:</td>
                    <td style="border:1px solid #000; padding:6px; border-right:0;">{{ settings()->bank_ifsc ?? '' }}</td>
                </tr>
                <tr>
                    <td colspan="3" style="border:1px solid #000; padding:6px; border-left:0; border-bottom:0;">Goods once purchased cannot be returned back, Goods damage or replacement direct to contact supplier. All Glass & Fiber electric are not guaranteed.</td>
                    <td style="border:1px solid #000; padding:6px; text-align:center; vertical-align:middle; border-right:0; border-bottom:0;">For {{ settings()->company_name }}<br><br>Authorised Signatory</td>
                </tr>
            </table>
        </div>
</div>