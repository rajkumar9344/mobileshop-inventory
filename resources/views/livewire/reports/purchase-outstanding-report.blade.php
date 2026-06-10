<div>
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center py-2">
                    <i class="bi bi-funnel-fill mr-2" style="color:#f97316;font-size:14px;"></i>
                    <strong>Filters</strong>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="$refresh">
                        <div class="form-row align-items-end">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Supplier Name</label>
                                    <select wire:model.live="supplier_id" class="form-control">
                                        <option value="">All Suppliers</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Purchase Bill Ref No</label>
                                    <input wire:model.live.debounce.300ms="reference" type="text" class="form-control" placeholder="Enter Bill Reference">
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Aging Range</label>
                                    <select wire:model.live="aging_range" class="form-control">
                                        <option value="">All</option>
                                        @foreach($agingRanges as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label class="d-block">&nbsp;</label>
                                    <button type="button" wire:click="resetFilters" class="btn btn-outline-danger btn-block">
                                        <i class="bi bi-arrow-clockwise"></i> Reset Filters
                                    </button>
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
                        <h5 class="mb-0">Purchase Bills Outstanding Report</h5>
                        <div>
                            <a href="{{ route('reports.purchase-outstanding-excel', [
                                'supplier_id' => $supplier_id,
                                'reference' => $reference,
                                'aging_range' => $aging_range,
                            ]) }}" target="_blank" class="btn btn-success mr-2">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>
                            <a href="{{ route('reports.purchase-outstanding-pdf', [
                                'supplier_id' => $supplier_id,
                                'reference' => $reference,
                                'aging_range' => $aging_range,
                            ]) }}" target="_blank" class="btn btn-danger mr-2">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </a>
                            <a href="{{ route('reports.purchase-outstanding-print', [
                                'supplier_id' => $supplier_id,
                                'reference' => $reference,
                                'aging_range' => $aging_range,
                            ]) }}" target="_blank" class="btn btn-secondary">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive position-relative">
                        <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center rm-loading-overlay" style="top:0;right:0;left:0;bottom:0;z-index: 99;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped text-center mb-0">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Purchase Bill Ref No</th>
                                    <th>Bill Date</th>
                                    <th>Bill Overall Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Balance Amount</th>
                                    <th>Bill Due Date</th>
                                    <th>Aging (Days)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchases as $purchase)
                                    @php
                                        $agingDays = \App\Livewire\Reports\PurchaseOutstandingReport::calculateAging($purchase->due_date);
                                        $badgeClass = \App\Livewire\Reports\PurchaseOutstandingReport::getAgingBadgeClass($agingDays);
                                        $billAmount = $purchase->overall_net_rate ?? $purchase->total_amount ?? 0;
                                        $paidAmount = $purchase->paid_amount ?? 0;
                                        $balanceAmount = $purchase->due_amount ?? ($billAmount - $paidAmount);
                                    @endphp
                                    <tr>
                                        <td class="text-left">{{ $purchase->supplier->supplier_name ?? $purchase->supplier_name ?? '-' }}</td>
                                        <td>
                                            <a href="{{ route('purchases.show', $purchase->id) }}" target="_blank">
                                                {{ $purchase->reference }}
                                            </a>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($purchase->date)->format('d-m-Y') }}</td>
                                        <td class="text-right">{{ number_format($billAmount, 2) }}</td>
                                        <td class="text-right">{{ number_format($paidAmount, 2) }}</td>
                                        <td class="text-right font-weight-bold text-danger">{{ number_format($balanceAmount, 2) }}</td>
                                        <td>{{ \Carbon\Carbon::parse($purchase->due_date)->format('d-m-Y') }}</td>
                                        <td>
                                            <span class="badge {{ $badgeClass }}">{{ $agingDays }} days</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <span class="text-success"><i class="bi bi-check-circle"></i> No Outstanding Bills Found!</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($purchases->count() > 0)
                                <tfoot style="background:var(--rm-bg-table-head);">
                                    <tr class="font-weight-bold">
                                        <td colspan="3" class="text-right text-dark font-weight-bold">Totals:</td>
                                        <td class="text-right text-dark font-weight-bold">{{ number_format($purchases->sum(fn($p) => $p->overall_net_rate ?? $p->total_amount ?? 0), 2) }}</td>
                                        <td class="text-right text-dark font-weight-bold">{{ number_format($purchases->sum('paid_amount'), 2) }}</td>
                                        <td class="text-right text-dark font-weight-bold">{{ number_format($purchases->sum('due_amount'), 2) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    <div @class(['mt-3' => $purchases->hasPages()])>
                        {{ $purchases->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
