{{--
    Shared stylesheet for all browser-print report views.
    Include inside <head> of each print view:

        @include('reports.partials._print-styles')                              ← portrait (default)
        @include('reports.partials._print-styles', ['orientation' => 'landscape'])

    After this include, add a second <style> block for view-specific rules
    (column widths, unique block styles, etc.).
--}}
@php $orientation = $orientation ?? 'portrait'; @endphp
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
        color: #000;
        background: #fff;
        padding: 20px;
    }

    /* ── Action bar (hidden on print) ── */
    .action-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        padding: 10px 14px;
        background: #f1f1f1;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    .action-bar button {
        padding: 7px 20px;
        font-size: 13px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
    }
    .btn-print        { background: #2d6a4f; color: #fff; }
    .btn-print:hover  { background: #1b4332; }
    .btn-close2       { background: #6c757d; color: #fff; }
    .btn-close2:hover { background: #495057; }
    .action-bar .record-count { margin-left: auto; font-size: 12px; color: #555; }

    /* ── Report header ── */
    .report-header {
        text-align: center;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 2px solid #222;
    }
    .report-header h2    { font-size: 17px; font-weight: bold; letter-spacing: 0.5px; margin-bottom: 3px; }
    .report-header .meta { font-size: 10px; color: #555; }

    /* ── Filters bar ── */
    .filters-bar {
        margin-bottom: 10px;
        font-size: 10.5px;
        color: #333;
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        padding: 5px 10px;
        border-radius: 4px;
    }
    .filters-bar span   { margin-right: 18px; }
    .filters-bar strong { color: #000; }

    /* ── Table base ── */
    table { width: 100%; border-collapse: collapse; }

    thead tr th {
        background-color: #1a1a2e;
        color: #fff;
        padding: 7px 5px;
        font-size: 10px;
        text-align: center;
        border: 1px solid #444;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    tbody tr td {
        padding: 5px;
        font-size: 10.5px;
        border: 1px solid #d0d0d0;
        vertical-align: middle;
        word-break: break-word;
    }

    tbody tr:nth-child(even) td {
        background-color: #f3f6fa;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Shared totals/summary row — use class="totals-row" on the <tr> */
    .totals-row td {
        background-color: #e8e8e8 !important;
        font-weight: bold;
        border: 1px solid #999;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .t-center { text-align: center; }
    .t-right  { text-align: right; }
    .t-left   { text-align: left; }

    .text-danger  { color: #c0392b; font-weight: bold; }
    .text-success { color: #27ae60; }

    /* ── Report footer ── */
    .report-footer {
        margin-top: 14px;
        font-size: 10px;
        color: #777;
        text-align: center;
        border-top: 1px solid #ddd;
        padding-top: 6px;
    }

    /* ── Print ── */
    @media print {
        .action-bar { display: none !important; }
        .print-tip  { display: none !important; }
        body { padding: 0; }
        @page {
            size: A4 {{ $orientation }};
            margin: 10mm 8mm;
        }
        thead { display: table-header-group; }
    }
</style>
