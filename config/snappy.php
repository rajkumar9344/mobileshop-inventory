<?php


return [
    /*
    |--------------------------------------------------------------------------
    | DomPDF Configuration
    |--------------------------------------------------------------------------
    |
    | This option contains settings for PDF generation using DomPDF.
    |
    */
    'dompdf' => [
        'enabled' => true,
        'options' => [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 96,
            'isPhpEnabled' => false,
            'isUnicodeEnabled' => true,
            'defaultMediaType' => 'screen',
            'defaultPaperSize' => 'a4',
            'fontSubsetting' => false,
            'defaultEncoding' => 'UTF-8',
            'enable_font_subsetting' => false,
            'fontHeightRatio' => 1.1,
        ],
        'orientation' => 'portrait',
    ],
];
