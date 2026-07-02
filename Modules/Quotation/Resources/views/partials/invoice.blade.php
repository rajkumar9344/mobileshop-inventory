<div class="invoice-root" style="width:190mm; margin:0 auto; font-family: Arial, Helvetica, sans-serif; font-size:10px; color:#000; box-sizing:border-box;">
    @php
        $is_pdf_request = $is_pdf_request ?? request()->is('quotations/pdf/*');
        $currency = function($amount) use ($is_pdf_request) {
            $formatted = number_format($amount, 2);
            return $is_pdf_request ? $formatted : (format_currency(0, true)[0] . $formatted);
        };
        $vatNo        = settings()->company_gst ?? '';
        $currencyCode = settings()->currency->code ?? '';
        $codeSuffix   = $currencyCode ? ' (' . $currencyCode . ')' : '';

        // Logo: absolute path for DomPDF, URL for browser
        $logoSrc = null;
        if (settings()->site_logo) {
            $rawPath = ltrim((string) settings()->site_logo, '/');
            $logoSrc = $is_pdf_request ? public_path($rawPath) : asset($rawPath);
        }

        // Arabic details
        $nameAr    = settings()->company_name_ar    ?? '';
        $addressAr = settings()->company_address_ar ?? '';

        // Compute totals once
        $total_subtotal = 0; $total_tax = 0; $total_grand = 0;
        $total_qty = 0; $vat_pct_label = '';
        $details = $quotation->quotationDetails;
        foreach ($details as $_item) {
            $vp = (float)($_item->tax_percentage ?? 0);
            if ($vat_pct_label === '' && $vp > 0) {
                $vat_pct_label = rtrim(rtrim(number_format($vp, 1), '0'), '.') . '%';
            }
            $_qty     = (float)($_item->quantity ?? 0);
            $_rate    = (float)($_item->rate ?? 0);
            $_vat_amt = (float)($_item->product_tax_amount ?? 0);
            $total_subtotal += $_qty * $_rate;
            $total_tax      += $_vat_amt;
            $total_grand    += $_qty * $_rate + $_vat_amt;
            $total_qty      += $_qty;
        }
        $vatLabel = 'VAT ' . ($vat_pct_label ?: '0%');

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
        $amountInWords = trim(($currencyCode ? $currencyCode . ' ' : '') . number_to_words($total_grand));
    @endphp

    {{-- ① HEADER: Logo (left) | vertical line | Company info (left-aligned, Arabic then English) --}}
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:20%; vertical-align:middle; padding:6px 10px 6px 0; text-align:center; border-right:1px solid #000;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" style="max-height:70px; max-width:100%; display:block; margin:0 auto;">
                @else
                    <div style="height:70px;"></div>
                @endif
            </td>
            <td style="vertical-align:middle; padding:6px 12px;">
                @if($nameAr)
                    <div style="font-size:16px; font-weight:bold; font-family:'Arial Unicode MS',Arial,sans-serif; margin-bottom:2px; direction:rtl; text-align:left; unicode-bidi:bidi-override;">{{ $nameAr }}</div>
                @endif
                <div style="font-size:15px; font-weight:800; letter-spacing:0.5px;">{{ strtoupper(settings()->company_name) }}</div>
                @if($addressAr)
                    <div style="font-size:11px; margin-top:2px; font-family:'Arial Unicode MS',Arial,sans-serif; color:#333; direction:rtl; text-align:left; unicode-bidi:bidi-override;">{{ $addressAr }}</div>
                @endif
                @if(settings()->company_address)
                    <div style="font-size:9px; margin-top:2px; color:#333;">{{ settings()->company_address }}</div>
                @endif
                <div style="font-size:9px; margin-top:3px;">
                    @if(settings()->company_phone)Tel: {{ settings()->company_phone }}{{ settings()->company_phone_2 ? ' / ' . settings()->company_phone_2 : '' }}@endif
                    @if(settings()->company_email) &nbsp;|&nbsp; {{ settings()->company_email }}{{ settings()->company_email_2 ? ' / ' . settings()->company_email_2 : '' }}@endif
                </div>
            </td>
        </tr>
    </table>
    {{-- GAP below header --}}
    <div style="height:14px;"></div>

    {{-- ② QUOTATION title (center) + TRN (right corner) --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
        <tr>
            <td style="width:30%;"></td>
            <td style="width:40%; text-align:center; vertical-align:middle; padding:4px 0;">
                <div style="font-size:17px; font-weight:900; letter-spacing:2px;">QUOTATION</div>
            </td>
            <td style="width:30%; text-align:right; vertical-align:middle; padding-right:2px;">
                @if($vatNo)
                    <div style="font-weight:700; font-size:10px;">TRN: {{ $vatNo }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ③ BILL TO (left box) | gap | Quotation details (right box) — rounded separate boxes --}}
    <table style="width:100%; border-collapse:separate; border-spacing:0;">
        <tr>
            <td style="width:49%; vertical-align:top; border:1px solid #ccc; border-radius:8px; padding:10px 12px;">
                <div style="font-size:8px; color:#999; font-weight:700; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.8px;">Bill To</div>
                <div style="font-weight:700; font-size:11px;">{{ $customer->customer_name ?? $quotation->customer_name ?? '' }}</div>
                @if(!empty($customer->address ?? ''))
                    <div style="margin-top:3px; font-size:9px; color:#555;">{{ $customer->address }}</div>
                @endif
                @if(!empty($customer->phone ?? ''))
                    <div style="margin-top:3px; font-size:9px; color:#555;">{{ $customer->phone }}</div>
                @endif
                @if(!empty($customer->vat_id ?? ''))
                    <div style="margin-top:8px; font-weight:700; font-size:10px;">TRN: {{ $customer->vat_id }}</div>
                @endif
            </td>
            <td style="width:2%;"></td>
            <td style="width:49%; vertical-align:top; border:1px solid #ccc; border-radius:8px; padding:0;">
                <table style="width:100%; border-collapse:collapse; font-size:10px;">
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:8px 12px; font-weight:700; width:42%; color:#555;">Quotation No</td>
                        <td style="padding:8px 4px; width:5%; color:#999;">:</td>
                        <td style="padding:8px 12px; font-weight:700;">{{ $quotation->reference }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px; font-weight:700; color:#555;">Date</td>
                        <td style="padding:8px 4px; color:#999;">:</td>
                        <td style="padding:8px 12px;">{{ \Carbon\Carbon::parse($quotation->date)->format('d-m-Y') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- GAP --}}
    <div style="height:10px;"></div>

    {{-- ④ PRODUCT TABLE (separate rounded box) --}}
    <div style="border:1px solid #ccc; border-radius:8px; overflow:hidden; margin-bottom:10px;">
        <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
            <colgroup>
                <col style="width:24px;">
                <col style="width:*;">
                <col style="width:38px;">
                <col style="width:54px;">
                <col style="width:58px;">
                <col style="width:52px;">
                <col style="width:58px;">
            </colgroup>
            <thead>
            <tr>
                <th style="border-bottom:2px solid #ccc; border-right:1px solid #ccc; padding:7px 4px; text-align:center; font-weight:700;">No.</th>
                <th style="border-bottom:2px solid #ccc; border-right:1px solid #ccc; padding:7px 6px; text-align:left; font-weight:700;">Description</th>
                <th style="border-bottom:2px solid #ccc; border-right:1px solid #ccc; padding:7px 3px; text-align:center; font-weight:700;">Qty</th>
                <th style="border-bottom:2px solid #ccc; border-right:1px solid #ccc; padding:7px 4px; text-align:center; font-weight:700;">U/Price{{ $codeSuffix }}</th>
                <th style="border-bottom:2px solid #ccc; border-right:1px solid #ccc; padding:7px 4px; text-align:center; font-weight:700;">Net Amt{{ $codeSuffix }}</th>
                <th style="border-bottom:2px solid #ccc; border-right:1px solid #ccc; padding:7px 3px; text-align:center; font-weight:700;">VAT Amt{{ $codeSuffix }}</th>
                <th style="border-bottom:2px solid #ccc; padding:7px 4px; text-align:center; font-weight:700;">Total Amt{{ $codeSuffix }}</th>
            </tr>
            </thead>
            <tbody>
            @php $i = 1; @endphp
            @foreach($details as $item)
                @php
                    $qty       = (float)($item->quantity ?? 0);
                    $rate      = (float)($item->rate ?? 0);
                    $vat_amt   = (float)($item->product_tax_amount ?? 0);
                    $subtotal  = $qty * $rate;
                    $linetotal = $subtotal + $vat_amt;
                    $rb = !$loop->last ? 'border-bottom:1px solid #eee;' : '';
                @endphp
                <tr>
                    <td style="{{ $rb }} border-right:1px solid #ccc; padding:6px 4px; text-align:center;">{{ $i++ }}</td>
                    <td style="{{ $rb }} border-right:1px solid #ccc; padding:6px 6px; white-space:normal; word-break:break-word;">{{ $item->product_name ?? '' }}</td>
                    <td style="{{ $rb }} border-right:1px solid #ccc; padding:6px 3px; text-align:center;">{{ $qty }}</td>
                    <td style="{{ $rb }} border-right:1px solid #ccc; padding:6px 4px; text-align:right;">{{ $currency($rate) }}</td>
                    <td style="{{ $rb }} border-right:1px solid #ccc; padding:6px 4px; text-align:right;">{{ $currency($subtotal) }}</td>
                    <td style="{{ $rb }} border-right:1px solid #ccc; padding:6px 3px; text-align:right;">{{ $currency($vat_amt) }}</td>
                    <td style="{{ $rb }} padding:6px 4px; text-align:right;">{{ $currency($linetotal) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- ⑤ TOTALS TABLE (separate rounded box) --}}
    <div style="border:1px solid #ccc; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:10px;">
            <tr>
                <td style="width:55%; vertical-align:top; padding:10px 12px; border-right:1px solid #eee;">
                    <div style="font-weight:700;">Total Quantity: {{ $total_qty }}</div>
                    <div style="margin-top:6px; font-size:9px; word-break:break-word;">{{ $amountInWords }}</div>
                </td>
                <td style="width:45%; vertical-align:top; padding:0;">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="padding:7px 12px; border-bottom:1px solid #eee;">TOTAL</td>
                            <td style="padding:7px 12px; text-align:right; border-bottom:1px solid #eee;">{{ $currency($total_subtotal) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:7px 12px; border-bottom:1px solid #eee;">{{ $vatLabel }}</td>
                            <td style="padding:7px 12px; text-align:right; border-bottom:1px solid #eee;">{{ $currency($total_tax) }}</td>
                        </tr>
                        <tr>
                            <td style="padding:7px 12px; font-weight:800;">NET TOTAL</td>
                            <td style="padding:7px 12px; text-align:right; font-weight:800;">{{ $currency($total_grand) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    @if($quotation->note)
        <div style="margin-top:8px; font-size:10px;"><strong>Remarks:</strong> {{ $quotation->note }}</div>
    @endif

    {{-- GAP --}}
    <div style="height:50px;"></div>

    {{-- ⑤ SIGNATURES --}}
    <table style="width:100%; border-collapse:collapse; font-size:10px;">
        <tr>
            <td style="width:33%; text-align:center; padding:8px 10px;">
                <div>Received By</div>
            </td>
            <td style="width:34%; text-align:center; padding:8px 10px;">
                <div>Prepared By</div>
            </td>
            <td style="width:33%; text-align:center; padding:8px 10px;">
                <div>Authorized By</div>
            </td>
        </tr>
    </table>
</div>
