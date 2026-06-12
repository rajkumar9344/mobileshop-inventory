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

            <div wire:loading class="text-center py-2">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            </div>

            <div class="table-responsive mt-2">
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
