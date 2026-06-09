<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sale Return Details</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .pdf-container {
            width: 98%;
            margin: 0 auto;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            width: 180px;
        }
        .reference {
            text-align: center;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .info-table td {
            vertical-align: top;
            padding: 0 10px;
            border: none;
        }
        .info-title {
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
            margin-bottom: 8px;
            font-size: 15px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .details-table th, .details-table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
        }
        .details-table th {
            background: #f7f7f7;
        }
        .totals-table {
            width: 40%;
            float: right;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .totals-table td {
            padding: 6px 8px;
            border: none;
        }
        .totals-table tr td:first-child {
            text-align: left;
        }
        .totals-table tr td:last-child {
            text-align: right;
        }
        .footer {
            text-align: center;
            font-style: italic;
            margin-top: 40px;
            color: #888;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 11px;
            color: #fff;
            background: #28a745;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="pdf-container">
    @php
        $siteLogo = settings()->site_logo ?: asset('images/logo-dark.png');
    @endphp
    <div class="logo">
        <img src="{{ embed_image_for_pdf($siteLogo) }}" alt="Logo">
    </div>
    <div class="reference">
        <span>Reference:</span> <strong>{{ $sale_return->reference }}</strong>
    </div>
    <table class="info-table">
        <tr>
            <td width="33%">
                <div class="info-title">Company Info:</div>
                <div><strong>{{ settings()->company_name }}</strong></div>
                <div>{{ settings()->company_address }}</div>
                <div>Email: {{ settings()->company_email }}</div>
                <div>Phone: {{ settings()->company_phone }}</div>
            </td>
            <td width="33%">
                <div class="info-title">Customer Info:</div>
                <div><strong>{{ $customer->customer_name }}</strong></div>
                <div>{{ $customer->address }}</div>
                <div>Email: {{ $customer->customer_email }}</div>
                <div>Phone: {{ $customer->customer_phone }}</div>
            </td>
            <td width="34%">
                <div class="info-title">Invoice Info:</div>
                <div>Invoice: <strong>INV/{{ $sale_return->reference }}</strong></div>
                <div>Date: {{ \Carbon\Carbon::parse($sale_return->date)->format('d M, Y') }}</div>
                <div>Status: <strong>{{ $sale_return->status }}</strong></div>
                <div>Payment Status: <strong>{{ $sale_return->payment_status }}</strong></div>
            </td>
        </tr>
    </table>
    <table class="details-table">
        <thead>
        <tr>
            <th>Product</th>
            <th>Net Unit Price</th>
            <th>Quantity</th>
            <th>Discount</th>
            <th>Tax</th>
            <th>Sub Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($sale_return->saleReturnDetails as $item)
            <tr>
                <td>
                    {{ $item->product_name }} <br>
                    <span class="badge">{{ $item->productCode->code ?? $item->product_code }}</span>
                </td>
                <td>{{ format_currency($item->unit_price) }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ format_currency($item->product_discount_amount) }}</td>
                <td>{{ format_currency($item->product_tax_amount) }}</td>
                <td>{{ format_currency($item->sub_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals-table">
        <tr>
            <td><strong>Discount ({{ $sale_return->discount_percentage }}%)</strong></td>
            <td>{{ format_currency($sale_return->discount_amount) }}</td>
        </tr>
        <tr>
            <td><strong>Tax ({{ $sale_return->tax_percentage }}%)</strong></td>
            <td>{{ format_currency($sale_return->tax_amount) }}</td>
        </tr>
        <tr>
            <td><strong>Shipping</strong></td>
            <td>{{ format_currency($sale_return->shipping_amount) }}</td>
        </tr>
        <tr>
            <td><strong>Grand Total</strong></td>
            <td><strong>{{ format_currency($sale_return->total_amount) }}</strong></td>
        </tr>
    </table>
    <div class="footer">
        {{ settings()->company_name }} &copy; {{ date('Y') }}
    </div>
</div>
</body>
</html>
