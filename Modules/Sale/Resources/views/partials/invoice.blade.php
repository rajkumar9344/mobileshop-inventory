<div class="invoice-root" style="width:180mm; margin:0 auto; font-family: Arial, Helvetica, sans-serif; font-size:10px; color:#000; border:1px solid #000; box-sizing:border-box; position:relative; min-height:250mm;">
    @php
        $is_pdf_request = $is_pdf_request ?? request()->is('sales/pdf/*');
        $currency = function($amount) use ($is_pdf_request) {
            $formatted = number_format($amount, 2);
            return $is_pdf_request ? $formatted : (format_currency(0, true)[0] . $formatted);
        };
        $vatNo = settings()->company_gst ?? '';
    @endphp

    <div class="invoice-header" style="padding:8px 0;">
        <table style="width:100%; border-collapse:collapse; margin-bottom:6px;">
            <tr>
                <td style="width:25%; vertical-align:top; font-size:10px; padding-left:6px;">
                    @if($vatNo)
                        <div style="font-weight:700;">VAT No: {{ $vatNo }}</div>
                    @endif
                    <div style="margin-top:4px; font-size:10px;">Tel: {{ settings()->company_phone }}</div>
                </td>
                <td style="width:50%; vertical-align:top; text-align:center;">
                    <div style="font-weight:800; font-size:16px;">{{ strtoupper(settings()->company_name) }}</div>
                    <div style="font-size:10px; margin-top:3px;">{{ settings()->company_address }}</div>
                    <div style="font-size:10px; margin-top:2px;">{{ settings()->company_email ?? '' }}</div>
                </td>
                <td style="width:25%; vertical-align:top; text-align:right; font-size:10px; padding-right:6px;">
                    @php
                        $is_pdf = $is_pdf_request ?? request()->is('sales/pdf/*');
                        $g_src = embed_image_for_pdf(settings()->gpay_qr ?? null, $is_pdf);
                        $p_src = embed_image_for_pdf(settings()->phonepe_qr ?? null, $is_pdf);
                    @endphp
                    @if($g_src || $p_src)
                        <table style="border-collapse:collapse; margin-left:auto;">
                            <tr>
                                @if($g_src)
                                    <td style="padding:0 4px; text-align:center; vertical-align:top;">
                                        <img src="{{ $g_src }}" alt="GPay QR" style="width:55px; height:auto;">
                                        <div style="font-size:8px; margin-top:2px;">GPay</div>
                                    </td>
                                @endif
                                @if($p_src)
                                    <td style="padding:0 4px; text-align:center; vertical-align:top;">
                                        <img src="{{ $p_src }}" alt="PhonePe QR" style="width:55px; height:auto;">
                                        <div style="font-size:8px; margin-top:2px;">PhonePe</div>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        <table style="width:100%; border-collapse:collapse; margin-top:4px; font-size:10px;">
            <tr>
                <td style="border:1px solid #000; padding:5px; width:35%; border-left:0;">Invoice No: <strong>{{ $sale->reference }}</strong></td>
                <td style="border:1px solid #000; padding:5px; width:20%;">Type: {{ $sale->bill_type ?? 'CASH' }}</td>
                <td style="border:1px solid #000; padding:5px; width:25%;">Date: {{ \Carbon\Carbon::parse($sale->date)->format('d/m/Y') }}</td>
                <td style="border:1px solid #000; padding:5px; width:20%; border-right:0;">{{ \Carbon\Carbon::parse($sale->created_at ?? $sale->date)->format('h:i A') }}</td>
            </tr>
        </table>

        <table style="width:100%; border-collapse:collapse; margin-top:4px; font-size:10px;">
            <tr>
                <td style="padding-left:6px; padding-top:4px; padding-bottom:4px;">
                    <div style="font-weight:700; font-size:11px;">{{ $customer->customer_name ?? $sale->customer_name ?? '' }}</div>
                    @if(!empty($customer->address ?? ''))
                        <div>{{ $customer->address }}</div>
                    @endif
                    @if(!empty($customer->phone ?? ''))
                        <div>Tel: {{ $customer->phone }}</div>
                    @endif
                    @if(!empty($customer->vat_id ?? ''))
                        <div>VAT No: {{ $customer->vat_id }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="invoice-body" style="padding:0; margin-bottom:32mm;">
        <table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:10px; border-top:1px solid #000; border-bottom:1px solid #000; table-layout:fixed;">
            <colgroup>
                <col style="width:22px;">
                <col style="width:60px;">
                <col style="width:*;">
                <col style="width:30px;">
                <col style="width:60px;">
                <col style="width:45px;">
                <col style="width:65px;">
            </colgroup>
            <thead>
            <tr style="background:#f5f5f5;">
                <th style="border:1px solid #000; padding:5px; border-left:0; text-align:center;">No</th>
                <th style="border:1px solid #000; padding:5px; white-space:normal; word-break:break-word;">Code</th>
                <th style="border:1px solid #000; padding:5px; white-space:normal; word-break:break-word;">Product Name</th>
                <th style="border:1px solid #000; padding:5px; text-align:center;">Qty</th>
                <th style="border:1px solid #000; padding:5px; text-align:right;">Rate</th>
                <th style="border:1px solid #000; padding:5px; text-align:right;">Tax</th>
                <th style="border:1px solid #000; padding:5px; text-align:right; border-right:0;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @php $i=1; $total_tax=0; $total_value=0; $total_qty=0; @endphp
            @foreach($sale->saleDetails as $item)
                @php
                    $qty  = (float) ($item->quantity ?? 0);
                    $rate = (float) ($item->rate ?? 0);
                    $tax  = (float) ($item->product_tax_amount ?? 0);
                    $amount = $qty * $rate;
                    $total_tax   += $tax;
                    $total_value += $amount;
                    $total_qty   += $qty;
                @endphp
                <tr>
                    <td style="border:1px solid #000; padding:5px; text-align:center; border-left:0;">{{ $i++ }}</td>
                    <td style="border:1px solid #000; padding:5px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $item->productCode->code ?? $item->product_code ?? '' }}</td>
                    <td style="border:1px solid #000; padding:5px; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $item->product_name ?? '' }}</td>
                    <td style="border:1px solid #000; padding:5px; text-align:center;">{{ $qty }}</td>
                    <td style="border:1px solid #000; padding:5px; text-align:right;">{{ $currency($rate) }}</td>
                    <td style="border:1px solid #000; padding:5px; text-align:right;">{{ $currency($tax) }}</td>
                    <td style="border:1px solid #000; padding:5px; text-align:right; border-right:0;">{{ $currency($amount) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td style="border-top:1px solid #000; padding:4px; border-left:0;"></td>
                <td style="border-top:1px solid #000; padding:4px;"></td>
                <td style="border-top:1px solid #000; padding:4px; text-align:right; font-weight:700;">Total</td>
                <td style="border-top:1px solid #000; padding:4px; text-align:center; font-weight:700;">{{ $total_qty }}</td>
                <td style="border-top:1px solid #000; padding:4px;"></td>
                <td style="border-top:1px solid #000; padding:4px; text-align:right;">{{ $currency($total_tax) }}</td>
                <td style="border-top:1px solid #000; padding:4px; text-align:right; font-weight:700; border-right:0;">{{ $currency($total_value) }}</td>
            </tr>
            </tfoot>
        </table>

        @php
            $finalDiscount = $sale->discount_amount ?? 0;
            $displayTotal  = ($sale->overall_net_rate ?? $sale->total_amount ?? 0) - $finalDiscount;
        @endphp

        <table style="width:100%; border-collapse:collapse; margin-top:4px; font-size:10px;">
            <tr>
                <td style="width:55%; vertical-align:top; padding-left:6px; padding-top:4px;">
                    @if($sale->note)
                        <div style="font-size:9px; color:#555;">Note: {{ $sale->note }}</div>
                    @endif
                </td>
                <td style="width:45%; vertical-align:top; padding-right:6px;">
                    <table style="width:100%; border-collapse:collapse;">
                        @if($total_tax > 0)
                        <tr>
                            <td style="padding:3px; text-align:right;">Tax Amount</td>
                            <td style="padding:3px; text-align:right; width:70px;">{{ $currency($total_tax) }}</td>
                        </tr>
                        @endif
                        @if($finalDiscount > 0)
                        <tr>
                            <td style="padding:3px; text-align:right;">Discount</td>
                            <td style="padding:3px; text-align:right;">-{{ $currency($finalDiscount) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="padding:3px; text-align:right; font-weight:700; border-top:1px solid #000; font-size:11px;">Grand Total</td>
                            <td style="padding:3px; text-align:right; font-weight:700; border-top:1px solid #000; font-size:11px;">{{ $currency($displayTotal) }}</td>
                        </tr>
                        @if($sale->paid_amount > 0)
                        <tr>
                            <td style="padding:3px; text-align:right; color:#333;">Paid</td>
                            <td style="padding:3px; text-align:right; color:#333;">{{ $currency($sale->paid_amount) }}</td>
                        </tr>
                        @endif
                        @if($sale->due_amount > 0)
                        <tr>
                            <td style="padding:3px; text-align:right; font-weight:700; color:#c00;">Balance Due</td>
                            <td style="padding:3px; text-align:right; font-weight:700; color:#c00;">{{ $currency($sale->due_amount) }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="invoice-footer" style="position:absolute; bottom:0; left:0; right:0; box-sizing:border-box;">
        <table style="width:100%; border-collapse:collapse; font-size:9px;">
            @if(settings()->bank_name || settings()->bank_account)
            <tr>
                <td style="border-top:1px solid #ccc; padding:4px 6px; width:20%;">Bank:</td>
                <td style="border-top:1px solid #ccc; padding:4px 6px; width:30%;">{{ settings()->bank_name ?? '' }}</td>
                <td style="border-top:1px solid #ccc; padding:4px 6px; width:15%;">A/c No:</td>
                <td style="border-top:1px solid #ccc; padding:4px 6px; width:35%;">{{ settings()->bank_account ?? '' }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="3" style="border-top:1px solid #ccc; padding:5px 6px; font-size:9px; color:#555;">
                    Goods once sold cannot be returned. All disputes subject to local jurisdiction.
                </td>
                <td style="border-top:1px solid #ccc; padding:5px 6px; text-align:center; font-size:9px;">
                    For {{ settings()->company_name }}<br><br>Authorised Signatory
                </td>
            </tr>
        </table>
        <div style="text-align:center; padding:4px; font-size:9px; color:#555; border-top:1px solid #eee;">
            Thank you for your purchase!
        </div>
    </div>
</div>
