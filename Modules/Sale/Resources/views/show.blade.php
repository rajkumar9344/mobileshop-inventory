@extends('layouts.app')

@section('title', 'Sales Invoice')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
        <li class="breadcrumb-item active">Invoice</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center">
                        <div>
                            Reference: <strong>{{ $sale->reference }}</strong>
                        </div>
                        <div class="ml-auto">
                            <a href="{{ route('sales.index') }}" class="btn btn-sm btn-outline-secondary mr-2">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            @if(isset($sale->payment_status) && ($sale->payment_status === 'Paid' || $sale->payment_status === 'Partial'))
                                <a href="{{ route('sales.pdf', $sale->id) }}" class="btn btn-sm btn-secondary" target="_blank">
                                    <i class="bi bi-printer"></i> Print
                                </a>
                                <a href="{{ route('sales.pdf', $sale->id) }}" class="btn btn-sm btn-primary ml-1" target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="invoice-print-content">
                            @include('sale::partials.invoice', compact('sale', 'customer'))
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openInvoicePrint() {
        try {
            const invoiceEl = document.querySelector('.invoice-print-content');
            if (!invoiceEl) {
                alert('Invoice content not found');
                return;
            }

            const invoiceHtml = invoiceEl.innerHTML;
            const w = window.open('', '_blank');
            if (!w) {
                alert('Popup blocked. Please allow popups for this site to print.');
                return;
            }

            const safeRef = 'sale-{{ preg_replace('/[\/\\\\]+/', '-', $sale->reference) }}';

            const css = `
                <style>
                    @page { size: A4; margin: 8mm; }
                    html,body{margin:0;padding:0;font-family: Arial, Helvetica, sans-serif;color:#000}
                    .invoice-root{width:100%;box-sizing:border-box}
                    table{border-collapse:collapse}
                    /* Anchor footer to bottom for print preview */
                    .invoice-root { position: relative; min-height: 277mm; box-sizing: border-box; width: 180mm; margin: 0 auto; }
                    .invoice-footer { position: absolute; bottom: 8mm; left: 0; right: 0; }
                </style>
            `;

            w.document.open();
            w.document.write('<!doctype html><html><head><title>' + safeRef + '</title>' + css + '</head><body class="invoice-root">' + invoiceHtml + '</body></html>');
            w.document.close();
            try { w.document.title = safeRef; } catch (e) { /* ignore */ }
            w.focus();

            // Wait briefly for resources to render, then print
            setTimeout(function(){
                try {
                    w.print();
                } catch (e) {
                    console.error('Print failed', e);
                }
            }, 500);
        } catch (err) {
            console.error(err);
            alert('Unable to print invoice. See console for details.');
        }
    }
</script>
@endpush

