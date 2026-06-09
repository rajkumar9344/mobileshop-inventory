<div>
    <div class="card mb-3">
        <div class="card-body">
                <form wire:submit.prevent="$refresh" class="mb-0 gstr-filters">
                    <div class="form-row align-items-center">
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label>HSN Number</label>
                            <input type="text" wire:model.live="hsn" class="form-control" placeholder="HSN">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" wire:model.live="product" class="form-control" placeholder="Product Name">
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="form-group">
                            <label>Rate (GST %)</label>
                            <select wire:model.live="rate" class="form-control">
                                <option value="">All</option>
                                @foreach($rates as $r)
                                    @php
                                        $rf = (float) $r;
                                        // Use $rateLabel (not $label) to avoid leaking into daterange-filter $label variable
                                        $rateLabel = (floor($rf) == $rf) ? (int)$rf : rtrim(rtrim((string)$rf, '0'), '.');
                                    @endphp
                                    <option value="{{ $r }}">{{ $rateLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" wire:model.live="hide_without_hsn" id="hide-without-hsn">
                                <label class="form-check-label" for="hide-without-hsn">Hide products without HSN</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label>Reporting Period</label>
                            <div class="daterange-compact">
                                @include('components.daterange-filter', ['tableId' => 'gstr-report', 'noWrapper' => true])
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1">
                        <div class="form-group">
                            <label class="d-block">&nbsp;</label>
                            <button type="button" onclick="clearGstrDateRangeUI()" wire:click="resetFilters" class="btn btn-outline-danger btn-block" title="Reset Filters"><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">GSTR Report</h5>
                
                    <div>
                        <a href="{{ route('reports.gstr-excel', array_merge(request()->query(), ['hsn' => $hsn, 'product' => $product, 'rate' => $rate, 'hide_without_hsn' => $hide_without_hsn, 'start_date' => $start_date, 'end_date' => $end_date])) }}" class="btn btn-success">Export Excel</a>
                        <a href="{{ route('reports.gstr-pdf', array_merge(request()->query(), ['hsn' => $hsn, 'product' => $product, 'rate' => $rate, 'hide_without_hsn' => $hide_without_hsn, 'start_date' => $start_date, 'end_date' => $end_date])) }}" class="btn btn-danger">Export PDF</a>
                        <a href="{{ route('reports.gstr-print', array_merge(request()->query(), ['hsn' => $hsn, 'product' => $product, 'rate' => $rate, 'hide_without_hsn' => $hide_without_hsn, 'start_date' => $start_date, 'end_date' => $end_date])) }}" target="_blank" class="btn btn-secondary"><i class="bi bi-printer"></i> Print</a>
                    </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped text-center mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>HSN</th>
                            <th>Description</th>
                            <th>UQC</th>
                            <th>Quantity</th>
                            <th>Total Value (MRP)</th>
                            <th>Taxable Value</th>
                            <th>IGST</th>
                            <th>CGST</th>
                            <th>SGST</th>
                            <th>Cess</th>
                            <th>Rate %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->hsn ?? ($row->product->hsn ?? '-') }}</td>
                                <td class="text-left">{{ $row->product_name }}</td>
                                <td>{{ $row->product->product_unit ?? '-' }}</td>
                                <td>{{ $row->quantity }}</td>
                                <td class="text-right">{{ number_format(($row->mrp ?? 0) * $row->quantity, 2) }}</td>
                                <td class="text-right">{{ number_format(($row->rate ?? 0) * $row->quantity, 2) }}</td>
                                @php
                                    $igst = 0; $cgst = 0; $sgst = 0;
                                    $taxable = ($row->rate ?? 0) * ($row->quantity ?? 0);
                                    $lineTax = round($taxable * (($row->tax_percentage ?? 0) / 100), 2);
                                    if (!empty($row->sale) && ($row->sale->overall_igst ?? 0) > 0) {
                                        $igst = $lineTax;
                                    } else {
                                        $cgst = $sgst = round($lineTax / 2, 2);
                                    }
                                @endphp
                                <td class="text-right">{{ number_format($igst, 2) }}</td>
                                <td class="text-right">{{ number_format($cgst, 2) }}</td>
                                <td class="text-right">{{ number_format($sgst, 2) }}</td>
                                <td class="text-right">0.00</td>
                                <td>{{ rtrim(rtrim((string)($row->tax_percentage ?? 0), '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11"><span class="text-warning">No records found</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
</div>

@push('page_scripts')
    <script>
        // Sync daterange as used in other reports (scoped to gstr-report instance)
        function emitDateRangeToLivewire(){
            var s = document.getElementById('filter-start-gstr-report') ? document.getElementById('filter-start-gstr-report').value : '';
            var e = document.getElementById('filter-end-gstr-report') ? document.getElementById('filter-end-gstr-report').value : '';
            var emitted = false;
            try {
                if (window.Livewire && typeof window.Livewire.emit === 'function') {
                    window.Livewire.emit('setDateRange', s, e);
                    emitted = true;
                }
            } catch (err) { /* ignore */ }

            try {
                if (!emitted && window.livewire && typeof window.livewire.emit === 'function') {
                    window.livewire.emit('setDateRange', s, e);
                    emitted = true;
                }
            } catch (err) { /* ignore */ }

            if (!emitted) {
                try {
                    window.dispatchEvent(new CustomEvent('setDateRange', { detail: { start: s, end: e } }));
                } catch (err) { /* ignore */ }
            }
        }

        // Handle both native DOM custom events and jQuery-triggered events
        document.addEventListener && document.addEventListener('daterange.updated', emitDateRangeToLivewire);
        if (window.jQuery) {
            window.jQuery(document).on('daterange.updated', function(){ emitDateRangeToLivewire(); });
        }

        // Listen for Livewire-triggered reset and clear the daterange UI (scoped)
        function clearGstrDateRangeUI(){
            try {
                var clearBtn = document.getElementById('clear-filters-gstr-report');
                if (clearBtn) { clearBtn.click(); return; }
                var dr = document.getElementById('filter-daterange-gstr-report');
                var s = document.getElementById('filter-start-gstr-report');
                var e = document.getElementById('filter-end-gstr-report');
                if (dr) dr.value = '';
                if (s) s.value = '';
                if (e) e.value = '';
                if (window.jQuery) window.jQuery(document).trigger('daterange.updated');
            } catch (err) { /* ignore */ }
        }

        if (window.Livewire && typeof window.Livewire.on === 'function') {
            window.Livewire.on('gstrResetFilters', clearGstrDateRangeUI);
        }

        if (window.livewire && typeof window.livewire.on === 'function') {
            window.livewire.on('gstrResetFilters', clearGstrDateRangeUI);
        }
    </script>
@endpush

@push('page_css')
    <style>
        /* Compact the daterange component for this report and hide redundant buttons */
        /* Reduce padding so the daterange block fits nicely in the column */
        .daterange-compact .filter-panel { padding: 0.25rem 0.5rem; }
        /* Override the component's inline max-width so the input fills the column */
        .daterange-compact .filter-panel .input-group { max-width: none !important; width:100% !important; }
        .daterange-compact { padding-right: 0; }
        .daterange-compact #apply-filters-gstr-report,
        .daterange-compact #clear-filters-gstr-report,
        .daterange-compact .filter-shortcuts { display: none !important; }
        /* Reduce label size to keep filters compact */
        .gstr-filters .form-group label { font-size: 0.85rem; margin-bottom: .25rem; }
        /* Make the reset button compact and vertically centered */
        .daterange-compact .btn { padding: .25rem .5rem; font-size: .85rem; }
    </style>
@endpush

@push('page_scripts')
    <script>
        // Hide the daterangepicker's built-in Apply/Cancel buttons when opened (keeps UI compact)
        (function(){
            var el = document.getElementById('filter-daterange-gstr-report');
            if (!el) return;
            el.addEventListener('show.daterangepicker', function(){
                setTimeout(function(){
                    document.querySelectorAll('.daterangepicker .applyBtn, .daterangepicker .cancelBtn').forEach(function(b){
                        b.style.display = 'none';
                    });
                }, 0);
            });
        })();
    </script>
@endpush
