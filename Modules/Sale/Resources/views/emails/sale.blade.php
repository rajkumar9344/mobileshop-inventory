<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $sale->reference }}</title>
    <style>
        body { margin:0; padding:0; background:#f5f7fb; font-family: Arial, Helvetica, sans-serif; }
        .wrap { padding:24px 12px; }
        .card { max-width:680px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 6px 18px rgba(15,23,35,0.08); }
        .banner { background:linear-gradient(90deg,#1e8dd6,#2ac7f2); color:#fff; padding:20px 24px; }
        .banner-table { width:100%; border-collapse:collapse; }
        .banner-table td { vertical-align:middle; }
        .company-details { line-height:1.3; text-align:left; }
        .title { font-size:18px; margin:0; font-weight:700; color:#fff; }
        .subtitle { margin:0; opacity:0.95; font-size:13px; color:#fff; }
        .invoice-meta { text-align:right; }
        .invoice-meta .label { font-weight:700; font-size:14px; color:#fff; }
        .invoice-meta .ref { font-size:13px; opacity:0.95; margin-top:4px; color:#fff; }
        .body { padding:20px 28px; color:#2b2f33; }
        .cols-table { width:100%; border-collapse:collapse; margin:10px 0; }
        .cols-table td { vertical-align:top; padding:0 10px 0 0; width:50%; }
        .cols-table td:last-child { padding:0 0 0 10px; }
        .col-header { margin:0 0 8px 0; font-size:13px; color:#1f2d3d; border-bottom:1px solid #eef2f6; padding-bottom:8px; font-weight:700; }
        .muted { color:#667085; font-size:13px; }
        .message { margin:16px 0; padding:12px; background:#fbfdff; border:1px solid #eef5fb; border-radius:6px; }
        .cta { display:inline-block; margin-top:10px; background:#00a86b; color:#fff; padding:10px 18px; border-radius:24px; text-decoration:none; font-weight:600; }
        .total { text-align:center; margin-top:10px; font-size:16px; font-weight:700; color:#0f5132; }
        .footer { background:#fbfdff; padding:14px 20px; text-align:center; color:#6b7280; font-size:13px; }
        @media (max-width:640px){ .cols-table td { display:block; width:100%; padding:0 0 12px 0; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="banner">
            <table class="banner-table">
                <tr>
                    <td class="company-details">
                        <div class="title">{{ settings()->company_name }}</div>
                        <div class="subtitle">{{ settings()->company_tagline ?? 'Invoice from our company' }}</div>
                    </td>
                    <td class="invoice-meta">
                        <div class="label">Invoice</div>
                        <div class="ref">{{ $sale->reference }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="body">
            <div style="text-align:center; margin-bottom:8px;">
                <div class="muted">Invoice Date</div>
                <div style="font-weight:700">{{ \Carbon\Carbon::parse($sale->date)->format('d/m/Y h:i A') }}</div>
            </div>

            <table class="cols-table">
                <tr>
                    <td>
                        <div class="col-header">From</div>
                        <div class="muted">{{ settings()->company_name }}</div>
                        <div class="muted">{{ settings()->company_address }}</div>
                        <div class="muted">Email: {{ settings()->company_email }}</div>
                        <div class="muted">Phone: {{ settings()->company_phone }}</div>
                    </td>
                    <td>
                        <div class="col-header">To</div>
                        <div class="muted">{{ $customer->customer_name }}</div>
                        <div class="muted">{{ $customer->address }}</div>
                        <div class="muted">Email: {{ $customer->customer_email }}</div>
                        <div class="muted">Phone: {{ $customer->customer_phone }}</div>
                    </td>
                </tr>
            </table>

            <div class="message">
                <p style="margin:0 0 8px 0">Hello {{ $customer->customer_name }},</p>
                <p style="margin:0">Attached is your invoice. You can download the PDF attachment for your records.</p>
            </div>

            <div class="total">Total Amount: {{ format_currency($sale->overall_net_rate ?: $sale->overall_amount ?: $sale->total_amount ?: 0) }}</div>
        </div>

        <div class="footer">
            <div>Need help? Contact us at <a href="mailto:{{ settings()->company_email }}">{{ settings()->company_email }}</a></div>
            <div style="margin-top:6px">&copy; {{ date('Y') }} {{ settings()->company_name }}. All rights reserved.</div>
        </div>
    </div>
</div>
</body>
</html>