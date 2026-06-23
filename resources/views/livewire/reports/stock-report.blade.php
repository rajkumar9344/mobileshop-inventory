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
                                    <label>Product Category</label>
                                    <select wire:model.live="category_id" class="form-control">
                                        <option value="">All Categories</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Shop Name (Supplier)</label>
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
                                    <label>Compatibility</label>
                                    <input wire:model.live.debounce.300ms="compatibility" type="text" class="form-control" placeholder="Enter Compatibility">
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Search Product</label>
                                    <input wire:model.live.debounce.300ms="search" type="text" class="form-control" placeholder="Product name or code">
                                </div>
                            </div>
                        </div>
                        <div class="form-row align-items-end">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Generated Date From</label>
                                    <input wire:model.live="generated_date_from" type="date" class="form-control">
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Generated Date To</label>
                                    <input wire:model.live="generated_date_to" type="date" class="form-control">
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label class="d-block">&nbsp;</label>
                                    <button type="button" wire:click="resetFilters" class="btn btn-outline-danger btn-block" title="Clear all filters">
                                        <i class="bi bi-x-lg"></i>
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
                        <h5 class="mb-0">Reorder Report</h5>
                        <div>
                            <a href="{{ route('reports.reorder-excel', [
                                'category_id' => $category_id,
                                'supplier_id' => $supplier_id,
                                'compatibility' => $compatibility,
                                'generated_date_from' => $generated_date_from,
                                'generated_date_to' => $generated_date_to,
                                'search' => $search,
                            ]) }}" target="_blank" class="btn btn-success mr-2">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </a>
                            <a href="{{ route('reports.reorder-pdf', [
                                'category_id' => $category_id,
                                'supplier_id' => $supplier_id,
                                'compatibility' => $compatibility,
                                'generated_date_from' => $generated_date_from,
                                'generated_date_to' => $generated_date_to,
                                'search' => $search,
                            ]) }}" target="_blank" class="btn btn-danger mr-2">
                                <i class="bi bi-file-earmark-pdf"></i> Export PDF
                            </a>
                            <a href="{{ route('reports.reorder-print', [
                                'category_id' => $category_id,
                                'supplier_id' => $supplier_id,
                                'compatibility' => $compatibility,
                                'generated_date_from' => $generated_date_from,
                                'generated_date_to' => $generated_date_to,
                                'search' => $search,
                            ]) }}" target="_blank" class="btn btn-secondary">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>
                    
                    <div class="table-responsive position-relative">
                        <div wire:loading.flex class="position-absolute justify-content-center align-items-center" style="top:0;left:0;right:0;bottom:0;z-index:10;background:rgba(255,255,255,0.8);">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
                                <div class="small text-muted mt-2">Loading...</div>
                            </div>
                        </div>
                        <table class="table table-bordered table-striped text-center mb-0">
                            <thead>
                                    <tr>
                                        <th>Product Category</th>
                                        <th>Product Name (Code)</th>
                                        <th>Compatibility</th>
                                        <th>Shop Name (Supplier)</th>
                                        <th>Alert Quantity</th>
                                        <th>Current Overall Stock</th>
                                        <th>Reorder Quantity</th>
                                        <th>Generated Date</th>
                                    </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    @php
                                        $stockClass = $product->product_quantity <= 0 ? 'text-danger font-weight-bold' : ($product->product_quantity < $product->product_stock_alert ? 'text-warning' : '');
                                    @endphp
                                    <tr>
                                        <td class="text-left">{{ $product->category->category_name ?? '-' }}</td>
                                        <td class="text-left">{{ $product->product_name }} ({{ $product->product_code }})</td>
                                        <td>{{ $product->product_note ?? '-' }}</td>
                                        <td class="text-left">{{ $product->supplier->supplier_name ?? '-' }}</td>
                                        <td>{{ $product->product_stock_alert }}</td>
                                        <td class="{{ $stockClass }}">{{ $product->product_quantity }}</td>
                                        <td class="font-weight-bold text-primary">
                                            @if($product->product_quantity < $product->product_stock_alert)
                                                {{ $product->product_stock_alert - $product->product_quantity }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $product->created_at->format('d-m-Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <span class="text-success"><i class="bi bi-check-circle"></i> No Products Below Re-order Level!</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div @class(['mt-3' => $products->hasPages()])>
                        {{ $products->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>