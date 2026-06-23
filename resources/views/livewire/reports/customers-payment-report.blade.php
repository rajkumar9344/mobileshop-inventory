<div>
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center py-2">
                    <i class="bi bi-funnel-fill mr-2" style="color:#f97316;font-size:14px;"></i>
                    <strong>Filters</strong>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="$refresh" class="mb-0 customers-filters">
                        <div class="form-row align-items-center">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Customer Name</label>
                                    <select wire:model.live="customer_id" class="form-control">
                                        <option value="">All Customers</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">{{ $customer->customer_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Sales Bill / Receipt Ref No</label>
                                    <input wire:model.live.debounce.300ms="reference" type="text" class="form-control" placeholder="Enter Bill or Receipt Reference">
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <div class="form-group">
                                    <label>Payment Mode</label>
                                    <select wire:model.live="payment_mode" class="form-control">
                                        <option value="">All</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="UPI Payment">UPI Payment</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Product return">Product return</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Reporting Period</label>
                                    <div class="daterange-compact">
                                        @include('components.daterange-filter', ['tableId' => 'customers-payment', 'noWrapper' => true])
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-1">
                                <div class="form-group">
                                    <label class="d-block">&nbsp;</label>
                                    <button type="button" onclick="clearCustomersDateRangeUI()" wire:click="resetFilters" class="btn btn-outline-danger btn-block" title="Clear all filters"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Customers Payment Report</h5>
                        <div>
                            <a href="{{ route('reports.customers-payment-excel', [
                                'customer_id' => $customer_id,
                                'reference' => $reference,
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'payment_mode' => $payment_mode,
                            ]) }}" target="_blank" class="btn btn-success mr-2">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>
                            <a href="{{ route('reports.customers-payment-pdf', [
                                'customer_id' => $customer_id,
                                'reference' => $reference,
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'payment_mode' => $payment_mode,
                            ]) }}" target="_blank" class="btn btn-danger mr-2">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </a>
                            <a href="{{ route('reports.customers-payment-print', [
                                'customer_id' => $customer_id,
                                'reference' => $reference,
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'payment_mode' => $payment_mode,
                            ]) }}" target="_blank" class="btn btn-secondary">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive position-relative" style="min-height:120px;">
                        <div wire:loading.flex class="position-absolute justify-content-center align-items-center" style="top:0;left:0;right:0;bottom:0;z-index:10;background:rgba(255,255,255,0.8);">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
                                <div class="small text-muted mt-2">Loading...</div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped text-center mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer Name</th>
                                    <th>Sales Bill Ref No</th>
                                    <th>Sales Bill Date</th>
                                    <th>Sales Bill Overall Amount</th>
                                    <th>Receipt Ref</th>
                                    <th>Settled</th>
                                    <th>Received Amount</th>
                                    <th>Received Date</th>
                                    <th>Payment Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $line)
                                    <tr>
                                        <td>{{ $payments->firstItem() + $loop->index }}</td>
                                        <td class="text-left">{{ $line->receipt->customer->customer_name ?? '-' }}</td>
                                        <td>
                                            @if($line->sale)
                                                <a href="{{ route('sales.show', $line->sale->id) }}" target="_blank">{{ $line->sale->reference }}</a>
                                            @else
                                                {{ $line->bill_ref ?? '-' }}
                                            @endif
                                        </td>
                                        <td>{{ optional($line->sale && $line->sale->date ? \Carbon\Carbon::parse($line->sale->date) : null)->format('d-m-Y') ?? (optional($line->bill_date)->format('d-m-Y') ?? '-') }}</td>
                                        <td class="text-right">{{ number_format($line->bill_amount ?? ($line->sale ? ($line->sale->overall_amount ?? $line->sale->total_amount ?? 0) : 0), 2) }}</td>
                                        <td class="text-center">
                                            @if(!empty($line->receipt))
                                                <a href="{{ route('sales-receipts.show', $line->receipt->id) }}" target="_blank">
                                                    {{ $line->receipt->reference ?? ('RE' . str_pad($line->receipt->id, 5, '0', STR_PAD_LEFT)) }}
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">@if(isset($line->is_settled) && $line->is_settled) Yes @else No @endif</td>
                                        <td class="text-right">{{ number_format($line->payment_amount, 2) }}</td>
                                        <td>{{ optional($line->receipt->date ? \Carbon\Carbon::parse($line->receipt->date) : null)->format('d-m-Y') }}</td>
                                        <td>{{ $line->receipt->payment_mode ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10"><div class="text-center py-3 text-muted"><i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:4px;"></i>No data available</div></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div @class(['mt-3' => $payments->hasPages()])>
                        {{ $payments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page_css')
    <style>
        .daterange-compact .filter-panel { padding: 0.25rem 0.5rem; }
        .daterange-compact .filter-panel .input-group { max-width: none !important; width: 100% !important; }
        .daterange-compact #apply-filters-customers-payment,
        .daterange-compact #clear-filters-customers-payment,
        .daterange-compact .filter-shortcuts { display: none !important; }
        .daterange-compact .btn { padding: .25rem .4rem; font-size: .85rem; width:48px; }
        .customers-filters .form-group label { font-size: 0.85rem; margin-bottom: .25rem; }
    </style>
@endpush

@push('page_scripts')
    <script>
        function emitCustomersDateRangeToLivewire(){
            var s = document.getElementById('filter-start-customers-payment') ? document.getElementById('filter-start-customers-payment').value : '';
            var e = document.getElementById('filter-end-customers-payment') ? document.getElementById('filter-end-customers-payment').value : '';
            try { if (window.Livewire && typeof window.Livewire.emit === 'function') { window.Livewire.emit('setDateRange', s, e); return; } } catch (err) {}
            try { if (window.livewire && typeof window.livewire.emit === 'function') { window.livewire.emit('setDateRange', s, e); return; } } catch (err) {}
            try { window.dispatchEvent(new CustomEvent('setDateRange', { detail: { start: s, end: e } })); } catch (err){}
        }

        document.addEventListener && document.addEventListener('daterange.updated', emitCustomersDateRangeToLivewire);
        if (window.jQuery) { window.jQuery(document).on('daterange.updated', function(){ emitCustomersDateRangeToLivewire(); }); }

        function clearCustomersDateRangeUI(){
            try {
                var clearBtn = document.getElementById('clear-filters-customers-payment');
                if (clearBtn) { clearBtn.click(); return; }
                var dr = document.getElementById('filter-daterange-customers-payment');
                var s = document.getElementById('filter-start-customers-payment');
                var e = document.getElementById('filter-end-customers-payment');
                if (dr) dr.value = '';
                if (s) s.value = '';
                if (e) e.value = '';
                if (window.jQuery) window.jQuery(document).trigger('daterange.updated');
            } catch (err) {}
        }
    </script>
@endpush
