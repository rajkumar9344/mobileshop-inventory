<div>
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center">
            <h5 class="mb-0">Profit / Loss Report <small class="text-muted">(per sales bill)</small></h5>
            <div class="ml-auto">
                @php
                    $exportParams = array_filter([
                        'customer_id' => $customer_id,
                        'reference'   => $reference,
                        'start_date'  => $start_date,
                        'end_date'    => $end_date,
                        'pl_status'   => $pl_status,
                    ]);
                @endphp
                <a href="{{ route('reports.profit-loss-excel', $exportParams) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </a>
                <a href="{{ route('reports.profit-loss-pdf', $exportParams) }}" target="_blank" class="btn btn-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>
        </div>

        <div class="card-body">
            {{-- Filters --}}
            <div class="form-row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="mb-1">Customer</label>
                    <select wire:model.live="customer_id" class="form-control">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->customer_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="mb-1">Bill Ref No</label>
                    <input wire:model.live.debounce.400ms="reference" type="text" class="form-control" placeholder="e.g. SSA/00001">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="mb-1">From Date</label>
                    <input wire:model.live="start_date" type="date" class="form-control">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="mb-1">To Date</label>
                    <input wire:model.live="end_date" type="date" class="form-control">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="mb-1">Profit/Loss Status</label>
                    <select wire:model.live="pl_status" class="form-control">
                        <option value="">All</option>
                        <option value="profit">Profit</option>
                        <option value="loss">Loss</option>
                    </select>
                </div>
                <div class="col-md-1 mb-2">
                    <button wire:click="resetFilters" type="button" class="btn btn-outline-danger btn-block" title="Clear all filters">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            {{-- Listing --}}
            <div class="table-responsive mt-2 position-relative" style="min-height:120px;">
                <div wire:loading.flex class="position-absolute justify-content-center align-items-center" style="top:0;left:0;right:0;bottom:0;z-index:10;background:rgba(255,255,255,0.8);">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
                        <div class="small text-muted mt-2">Loading...</div>
                    </div>
                </div>
                <table class="table table-bordered table-striped table-sm mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Customer Name</th>
                        <th>Bill Ref No</th>
                        <th>Bill Date</th>
                        <th class="text-right">Overall Amount (Incl. VAT)</th>
                        <th class="text-right">Overall Amount (Without VAT)</th>
                        <th class="text-right">Purchased Rate Total</th>
                        <th class="text-right">Profit / Loss Amount</th>
                        <th class="text-center">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($sales as $i => $sale)
                        @php
                            $profit = $sale->profit_amount / 100;
                        @endphp
                        <tr>
                            <td>{{ $sales->firstItem() + $i }}</td>
                            <td>{{ $sale->customer->customer_name ?? $sale->customer_name ?? '-' }}</td>
                            <td>{{ $sale->reference }}</td>
                            <td>{{ \Carbon\Carbon::parse($sale->date)->format('d-m-Y') }}</td>
                            <td class="text-right">{{ format_currency($sale->amount_incl_vat / 100) }}</td>
                            <td class="text-right">{{ format_currency($sale->amount_excl_vat / 100) }}</td>
                            <td class="text-right">{{ format_currency($sale->purchase_total / 100) }}</td>
                            <td class="text-right {{ $profit >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                {{ format_currency(abs($profit)) }}
                            </td>
                            <td class="text-center">
                                @if($profit >= 0)
                                    <span class="badge badge-success">Profit</span>
                                @else
                                    <span class="badge badge-danger">Loss</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-3">No sales bills found for the selected filters.</td>
                        </tr>
                    @endforelse
                    </tbody>
                    @if($sales->isNotEmpty())
                    <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="4" class="text-right">Page Total</td>
                        <td class="text-right">{{ format_currency($pageTotals['incl_vat']) }}</td>
                        <td class="text-right">{{ format_currency($pageTotals['excl_vat']) }}</td>
                        <td class="text-right">{{ format_currency($pageTotals['purchase_total']) }}</td>
                        <td class="text-right {{ $pageTotals['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ format_currency(abs($pageTotals['profit'])) }} {{ $pageTotals['profit'] >= 0 ? '(Profit)' : '(Loss)' }}
                        </td>
                        <td></td>
                    </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            <div class="mt-3">
                {{ $sales->links() }}
            </div>
        </div>
    </div>
</div>
