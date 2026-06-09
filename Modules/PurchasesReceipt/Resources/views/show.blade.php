@extends('layouts.app')

@section('title', 'Purchases Receipt - ' . $receipt->reference)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div>
                        <h3 class="m-0">Purchases Receipt - {{ $receipt->reference }}</h3>
                    </div>
                    <div class="card-tools mfs-auto">
                        <a href="{{ route('purchases-receipts.index') }}" class="btn btn-sm btn-outline-secondary mr-2">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <button type="button" class="btn btn-sm btn-secondary mfe-1 d-print-none" onclick="openReceiptPrint()">
                            <i class="bi bi-printer"></i> Print
                        </button>

                        @php
                            $firstPurchaseId = $receipt->lines->pluck('purchase_id')->filter()->first();
                        @endphp

                        @if(Route::has('purchases-receipts.pdf'))
                            <a href="{{ route('purchases-receipts.pdf', $receipt->id) }}" class="btn btn-sm btn-primary mfe-1 d-print-none" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> PDF
                            </a>
                        @elseif(Route::has('purchases.pdf') && $firstPurchaseId)
                            {{-- Fallback: link to the first referenced purchase PDF so user still gets a PDF button like Purchase view --}}
                            <a href="{{ route('purchases.pdf', $firstPurchaseId) }}" class="btn btn-sm btn-primary mfe-1 d-print-none" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i> PDF
                            </a>
                        @else
                            <button class="btn btn-sm btn-primary mfe-1 d-print-none" disabled>
                                <i class="bi bi-file-earmark-pdf"></i> PDF
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="invoice-print-content">
                        {{-- Include corresponding purchase invoice(s) for the purchases referenced by this receipt --}}
                        @php
                            $includedPurchaseIds = [];
                        @endphp

                        @foreach($receipt->lines as $line)
                            @php
                                $pid = $line->purchase_id ?? $line->bill_id ?? null;
                            @endphp

                            @if($pid && !in_array($pid, $includedPurchaseIds))
                                @php $includedPurchaseIds[] = $pid; @endphp
                                @php
                                    $purchaseObj = $line->purchase ?? (isset($purchases[$pid]) ? $purchases[$pid] : null);
                                    $supplierObj = null;
                                    if ($purchaseObj) {
                                        $supId = $purchaseObj->supplier_id ?? ($purchaseObj->supplier->id ?? null);
                                        if (isset($suppliers) && $supId && isset($suppliers[$supId])) {
                                            $supplierObj = $suppliers[$supId];
                                        } else {
                                            $supplierObj = $purchaseObj->supplier ?? ($receipt->supplier ?? null);
                                        }
                                    } else {
                                        // Fallback to receipt supplier when purchase object not available
                                        $supplierObj = $receipt->supplier ?? null;
                                    }
                                @endphp

                                @if($purchaseObj)
                                    @include('purchase::partials.invoice', ['purchase' => $purchaseObj, 'supplier' => $supplierObj])
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