<div class="invoice-root" style="width:180mm; margin:0 auto; font-family: Arial, Helvetica, sans-serif; font-size:10px; color:#000; border:1px solid #000; padding:8px 0; box-sizing:border-box;">
    <div style="padding:8px 0;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between;">
            <div style="width:30%; font-size:10px; padding-left:6px;">
                <div style="font-weight:700;">GST NO: {{ settings()->company_gst ?? '' }}</div>
            </div>
            <div style="width:40%; text-align:center;">
                <div style="font-weight:800; font-size:16px;">{{ strtoupper(settings()->company_name) }}</div>
                <div style="font-size:10px; margin-top:3px;">{{ settings()->company_address }}</div>
                <div style="font-size:10px; margin-top:2px;">{{ settings()->company_email ?? '' }}</div>
            </div>
            <div style="width:28%; text-align:right; font-size:10px; padding-right:6px;">
                <div style="font-weight:700;">PH: {{ settings()->company_phone }}</div>
            </div>
        </div>

        <table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10px;">
            <tr>
                <td style="border:1px solid #000; padding:6px; width:30%; border-left:0;">Receipt No: <strong>{{ $receipt->reference }}</strong></td>
                <td style="border:1px solid #000; padding:6px; width:20%;">Date: {{ (!empty($receipt->date) && $receipt->date !== '-' && $receipt->date !== '0000-00-00') ? \Carbon\Carbon::parse($receipt->date)->format('d/m/Y') : '' }}</td>
                <td style="border:1px solid #000; padding:6px; width:50%;" colspan="3">Customer: <strong>{{ optional($receipt->customer)->customer_name }}</strong></td>
            </tr>
        </table>

        <table style="width:100%; border-collapse:collapse; margin-top:8px; font-size:10px; border-top:1px solid #000; border-bottom:1px solid #000;">
            <thead>
                <tr>
                    <th style="border:1px solid #000; padding:4px; width:80px;">Bill Ref</th>
                    <th style="border:1px solid #000; padding:4px; width:80px;">Bill Date</th>
                    <th style="border:1px solid #000; padding:4px; width:80px;">Bill Amount</th>
                    <th style="border:1px solid #000; padding:4px; width:80px;">Payment</th>
                    <th style="border:1px solid #000; padding:4px; width:80px; border-right:0;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @php $total_bill=0; $total_payment=0; $total_discount=0; $total_balance=0; @endphp
                @foreach($receipt->lines as $line)
                    @php
                        $total_bill += $line->bill_amount ?? 0;
                        $total_payment += $line->payment_amount ?? 0;
                        $total_discount += $line->discount_amount ?? 0;
                        $total_balance += $line->final_balance ?? 0;
                    @endphp
                    <tr>
                        <td style="border:1px solid #000; padding:6px;">{{ $line->bill_ref }}</td>
                        <td style="border:1px solid #000; padding:6px; text-align:center;">{{ $line->bill_date }}</td>
                        <td style="border:1px solid #000; padding:6px; text-align:right;">{{ format_currency($line->bill_amount) }}</td>
                        <td style="border:1px solid #000; padding:6px; text-align:right;">{{ format_currency($line->payment_amount) }}</td>
                        <td style="border:1px solid #000; padding:6px; text-align:right; border-right:0;">{{ format_currency($line->final_balance) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10px;">
            <tr>
                <td style="padding:4px;" colspan="2"></td>
                <td style="padding:4px; text-align:right;">{{ format_currency($total_bill) }}</td>
                <td style="padding:4px; text-align:right;">{{ format_currency($total_payment) }}</td>
                <td style="padding:4px; text-align:right;">{{ format_currency($total_balance) }}</td>
            </tr>
        </table>

        @php
            if (! function_exists('number_to_words')) {
                function number_to_words($number)
                {
                    $n = (int) round($number);
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
                    return 'Rupees ' . strtoupper($words) . ' ONLY';
                }
            }
        @endphp

        <div style="margin-top:6px; display:flex; align-items:flex-start;">
            <div style="width:60%; font-size:10px; padding-left:6px;">
                <div style="font-weight:700;">{{ number_to_words($total_payment) }}</div>
            </div>
            <div style="width:40%; font-size:10px;">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td colspan="5">&nbsp;</td>
                        <td style="padding:4px; text-align:right;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td colspan="5" style="font-weight:700; text-align:right;">Total Received</td>
                        <td style="padding:4px; text-align:right; font-weight:700; border-top:1px solid #000;">{{ format_currency($total_payment) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="invoice-footer" style="margin-top:12px;">
            <table style="width:100%; border-collapse:collapse; font-size:10px;">
                <tr>
                            <td colspan="3" style="border:1px solid #000; padding:6px; border-left:0;">Goods once sold cannot be returned back. All claims subject to manufacturer warranty.</td>
                            <td style="border:1px solid #000; padding:6px; text-align:center; vertical-align:middle; border-right:0;">For {{ settings()->company_name }}<br><br>Authorised Signatory</td>
                </tr>
                        @if(!empty(settings()->gpay_qr) || !empty(settings()->phonepe_qr))
                        <tr>
                            <td colspan="4" style="border:1px solid #000; padding:6px; border-left:0;">
                                <div style="display:flex; gap:8px; align-items:center;">
                                    @if(!empty(settings()->gpay_qr))
                                        <div style="min-width:80px;">
                                            <img src="{{ settings()->gpay_qr }}" alt="GPay QR" style="max-height:80px; max-width:100%;" onerror="this.style.display='none'">
                                        </div>
                                    @endif
                                    @if(!empty(settings()->phonepe_qr))
                                        <div style="min-width:80px;">
                                            <img src="{{ settings()->phonepe_qr }}" alt="PhonePe QR" style="max-height:80px; max-width:100%;" onerror="this.style.display='none'">
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endif
            </table>
        </div>
    </div>
</div>