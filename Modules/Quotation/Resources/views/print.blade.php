<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation</title>
    <style>
        @page { margin: 10mm; size: A4; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }
    </style>
</head>
<body>
    @php
        // Override format_currency for PDF to use Rs. instead of ₹ when needed
        if (!function_exists('format_currency_pdf')) {
            function format_currency_pdf($amount) {
                return 'Rs.' . number_format($amount, 2);
            }
        }
    @endphp
    @include('quotation::partials.invoice', compact('quotation', 'customer'))
</body>
</html>
