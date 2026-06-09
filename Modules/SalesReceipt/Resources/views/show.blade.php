@extends('layouts.app')

@section('title', 'Receipt Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales-receipts.index') }}">Receipts</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center">
                        <div>
                            Reference: <strong>{{ $receipt->reference }}</strong>
                        </div>
                        <div class="ml-auto">
                            <a href="{{ route('sales-receipts.index') }}" class="btn btn-sm btn-outline-secondary mr-2">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="openReceiptPrint()">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="invoice-print-content">
                            {{-- Include corresponding sale invoice(s) for the sales referenced by this receipt --}}
                            @php
                                // $sales and $customers are passed as keyed arrays (id => array) from controller
                                $includedSaleIds = [];
                            @endphp

                            @foreach($receipt->lines as $line)
                                @php $sid = $line->sale_id; @endphp
                                @if($sid && !in_array($sid, $includedSaleIds))
                                    @php $includedSaleIds[] = $sid; @endphp
                                    @php
                                        // Prefer the eager-loaded relation if present, otherwise try controller-provided map
                                        $saleObj = $line->sale ?? (isset($sales[$sid]) ? $sales[$sid] : null);
                                        $customerObj = null;
                                        if ($saleObj) {
                                            $custId = $saleObj->customer_id ?? null;
                                            if ($custId && isset($customers[$custId])) {
                                                $customerObj = $customers[$custId];
                                            } else {
                                                // fallback: attempt to load customer quietly
                                                $customerObj = \Modules\People\Entities\Customer::find($custId);
                                            }
                                        }
                                    @endphp

                                    @if($saleObj)
                                        @include('sale::partials.invoice', ['sale' => $saleObj, 'customer' => $customerObj])
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openReceiptPrint() {
        try {
            const invoiceEl = document.querySelector('.invoice-print-content');
            if (!invoiceEl) {
                alert('Receipt content not found');
                return;
            }

            const invoiceHtml = invoiceEl.innerHTML;
            const w = window.open('', '_blank');
            if (!w) {
                alert('Popup blocked. Please allow popups for this site to print.');
                return;
            }

            const safeRef = 'receipt-{{ str_replace(["/", "\\\\"], '-', $receipt->reference) }}';

            const css = `
                <style>
                    @page { size: A4; margin: 8mm; }
                    html,body{margin:0;padding:0;font-family: Arial, Helvetica, sans-serif;color:#000}
                    .invoice-root{width:100%;box-sizing:border-box}
                    table{border-collapse:collapse}
                    .invoice-root { position: relative; min-height: 277mm; box-sizing: border-box; width: 180mm; margin: 0 auto; }
                    .invoice-footer { position: absolute; bottom: 8mm; left: 0; right: 0; }
                </style>
            `;

            w.document.open();
            w.document.write('<!doctype html><html><head><title>' + safeRef + '</title>' + css + '</head><body class="invoice-root">' + invoiceHtml + '</body></html>');
            w.document.close();
            try { w.document.title = safeRef; } catch (e) { /* ignore */ }
            w.focus();

            setTimeout(function(){
                try { w.print(); } catch (e) { console.error('Print failed', e); }
            }, 500);
        } catch (err) {
            console.error(err);
            alert('Unable to print receipt. See console for details.');
        }
    }
</script>
@endpush
