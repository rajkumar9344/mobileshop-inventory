<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Invoice</title>
    <style>
        @page { margin: 10mm; size: A4; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }
    </style>
</head>
<body>
    @php
        // Override format_currency for PDF to use Rs. instead of ₹
        if (!function_exists('format_currency_pdf')) {
            function format_currency_pdf($amount) {
                return 'Rs.' . number_format($amount / 100, 2);
            }
        }
    @endphp
    @include('purchase::partials.invoice', compact('purchase', 'supplier'))
</body>
</html>
