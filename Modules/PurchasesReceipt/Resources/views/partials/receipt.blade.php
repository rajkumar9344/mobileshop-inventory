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
                <td style="border:1px solid #000; padding:6px; width:20%;">Date: {{ \Carbon\Carbon::parse($receipt->date)->format('d/m/Y') }}</td>
                <td style="border:1px solid #000; padding:6px; width:50%;" colspan="3">Supplier: <strong>{{ optional($receipt->supplier)->supplier_name }}</strong></td>
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
                <tr>
                    <td colspan="2" style="border:1px solid #000; padding:6px; text-align:right; font-weight:700;">Total</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right; font-weight:700;">{{ format_currency($total_bill) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right; font-weight:700;">{{ format_currency($total_payment) }}</td>
                    <td style="border:1px solid #000; padding:6px; text-align:right; font-weight:700; border-right:0;">{{ format_currency($total_balance) }}</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:8px; font-size:10px; padding:0 6px;">
            <div>Amount in Words: {{ number_to_words($receipt->total_amount) }}</div>
            <div style="margin-top:4px;">Payment Mode: {{ $receipt->payment_mode }}</div>
            <div style="margin-top:4px;">Particular: {{ $receipt->particular }}</div>
        </div>

        <div style="margin-top:20px; display:flex; justify-content:space-between; font-size:10px; padding:0 6px;">
            <div>Received By: ____________________</div>
            <div>Authorized By: ____________________</div>
        </div>
    </div>
</div>