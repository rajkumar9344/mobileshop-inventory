<div>
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h5 class="mb-0">Current Stock Report</h5>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="col-md-5 mb-2">
                    <label class="mb-1">Search</label>
                    <input wire:model.live.debounce.400ms="search" type="text" class="form-control" placeholder="Product name or code...">
                </div>
                <div class="col-md-1 mb-2">
                    <button wire:click="resetFilters" type="button" class="btn btn-outline-danger btn-block" title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

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
                        <th style="width:60px;">#</th>
                        <th>Product Name</th>
                        <th>Product Code</th>
                        <th class="text-center">Current Stock</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $i => $product)
                        <tr>
                            <td>{{ $products->firstItem() + $i }}</td>
                            <td>{{ $product->product_name }}</td>
                            <td>{{ $product->product_code }}</td>
                            <td class="text-center">{{ $product->product_quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">No products found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
