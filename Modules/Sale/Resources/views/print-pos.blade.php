<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title></title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            font-size: 12px;
            line-height: 18px;
            font-family: 'Ubuntu', sans-serif;
        }
        h2 {
            font-size: 16px;
        }
        td,
        th,
        tr,
        table {
            border-collapse: collapse;
        }
        tr {border-bottom: 1px dashed #ddd;}
        td,th {padding: 7px 0;width: 50%;}

        table {width: 100%;}
        tfoot tr th:first-child {text-align: left;}

        .centered {
            text-align: center;
            align-content: center;
        }
        small{font-size:11px;}

        @media print {
            * {
                font-size:12px;
                line-height: 20px;
            }
            td,th {padding: 5px 0;}
            .hidden-print {
                display: none !important;
            }
            tbody::after {
                content: '';
                display: block;
                page-break-after: always;
                page-break-inside: auto;
                page-break-before: avoid;
            }
        }
    </style>
</head>
<body>

<div style="max-width:400px;margin:0 auto">
    <div id="receipt-data">
        @php
            $is_pdf_request = request()->is('sales/pos/pdf/*');
            $pos_currency = function($value) use ($is_pdf_request) {
                $display = format_currency($value);
                if ($is_pdf_request) {
                    // Replace rupee glyph with 'Rs.' for PDF to avoid missing-glyph issues
                    return str_replace('₹', 'Rs.', $display);
                }
                return $display;
            };
        @endphp
        <div class="centered">
            <h2 style="margin-bottom: 5px">{{ settings()->company_name }}</h2>

            <p style="font-size: 11px;line-height: 15px;margin-top: 0">
                {{ settings()->company_email }}, {{ settings()->company_phone }}
                <br>{{ settings()->company_address }}
            </p>
        </div>
            <p>
            Date: {{ \Carbon\Carbon::parse($sale->date)->format('d M, Y') }}<br>
            Reference: {{ $sale->reference }}<br>
            Name: {{ $sale->customer->customer_name ?? '' }}
        </p>
        <table class="table-data">
            <tbody>
            @foreach($sale->saleDetails as $saleDetail)
                <tr>
                    <td colspan="2">
                        {{ $saleDetail->product->product_name }}
                        ({{ $saleDetail->quantity }} x {{ $pos_currency($saleDetail->rate) }})
                    </td>
                    <td style="text-align:right;vertical-align:bottom">{{ $pos_currency($saleDetail->sub_total) }}</td>
                </tr>
            @endforeach

            <tr>
                <th colspan="2" style="text-align:left">Grand Total</th>
                <th style="text-align:right">{{ $pos_currency($sale->total_amount) }}</th>
            </tr>
            </tbody>
        </table>
        <table>
            <tbody>
                <tr style="background-color:#ddd;">
                    <td class="centered" style="padding: 5px;">
                        Paid By: {{ $sale->payment_method }}
                    </td>
                    <td class="centered" style="padding: 5px;">
                        Amount: {{ $pos_currency($sale->paid_amount) }}
                    </td>
                </tr>
                <tr style="border-bottom: 0;">
                    <td class="centered" colspan="3">
                        <div style="margin-top: 10px;">
                            {!! \Milon\Barcode\Facades\DNS1DFacade::getBarcodeSVG($sale->reference, 'C128', 1, 25, 'black', false) !!}
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
