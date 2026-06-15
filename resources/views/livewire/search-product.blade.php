<div class="position-relative">
    {{-- Scoped utility class — rendered once to avoid injecting on every Livewire re-render --}}
    @once
    <style>
        .sp-meta { font-size: 15px; }
    </style>
    @endonce
    <div class="mb-3" style="position: relative; z-index: 2;">
        <div class="form-group mb-0">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <select wire:model.live="category_id" class="form-control">
                        <option value="">All Brands</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <div class="input-group-text">
                                <i class="bi bi-search text-primary"></i>
                            </div>
                        </div>
                        <input wire:keydown.escape="closeDropdown" wire:model.live.debounce.300ms="query" type="text" class="form-control" placeholder="Type product name or code...">
                    </div>
                </div>
                <div class="col-md-1 pl-0">
                    @if(!empty($category_id) || !empty($query))
                        <button wire:click="resetQuery" type="button" class="btn btn-outline-danger btn-block" title="Clear all filters">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div wire:loading wire:target="query,category_id" class="card position-absolute mt-1 border-0" style="z-index: 3;left: 0;right: 0;">
        <div class="card-body shadow">
            <div class="d-flex justify-content-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
        </div>
    </div>

    @if($showResults)
        {{-- Backdrop: closes dropdown but keeps the brand filter intact --}}
        <div wire:click="closeDropdown" class="position-fixed w-100 h-100" style="left: 0; top: 0; right: 0; bottom: 0;z-index: 1;"></div>
        @if($search_results->isNotEmpty())
            <div class="card position-absolute mt-1" style="z-index: 2;left: 0;right: 0;border: 0;max-height: 480px;overflow-y: auto;">
                <div class="card-body shadow p-0">
                    {{-- Result count header --}}
                    <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            @if($isLimited)
                                Showing first <strong>{{ $search_results->count() }}</strong> of <strong>{{ $totalCount }}</strong> product(s) &mdash;
                                <span class="text-primary">type 2+ letters to see all matches</span>
                            @else
                                Showing <strong>{{ $search_results->count() }}</strong>
                                @if($totalCount > $search_results->count())
                                    of <strong>{{ $totalCount }}</strong>
                                @endif
                                product(s)
                                @if(!empty($category_id))
                                    for brand <strong>{{ $search_results->first()->category ?? '' }}</strong>
                                @endif
                            @endif
                        </small>
                        <button wire:click="closeDropdown" type="button" class="close" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <ul class="list-group list-group-flush">
                        @php $stockLabel = ($context === 'purchase_return') ? 'Purchased Qty' : 'Stock'; @endphp
                        @foreach($search_results as $result)
                            <li class="list-group-item list-group-item-action">
                                <a wire:click.prevent="selectProduct({{ $result->id }})" href="#" class="d-block">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>{{ $result->product_name ?? $result->product_code ?? 'Unnamed Product' }}</strong><br>
                                            <span class="sp-meta text-muted">Code: 
                                                @if(!empty($result->product_codes) && is_array($result->product_codes))
                                                    {{ implode(', ', array_unique($result->product_codes)) }}
                                                @else
                                                    {{ $result->product_code }}
                                                @endif
                                            </span>
                                        </div>
                                        <div class="col-md-6 text-right">
                                            <span class="sp-meta">
                                                Brand: {{ $result->category ?? '-' }} |
                                                Purchase Rate: {{ format_currency($result->product_cost ?? 0, true, false) }} |
                                                Sell Rate: {{ format_currency($result->product_price ?? 0, true, false) }} |
                                                {{ $stockLabel }}: {{ $result->stock ?? $result->product_quantity }}
                                            </span><br>
                                            <span class="sp-meta">VAT: {{ $result->product_order_tax ?? 0 }}% | Unit: {{ $result->product_unit ?? '-' }}</span>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        @endforeach

                    </ul>
                </div>
            </div>
        @else
            {{-- Hide "No Product Found" while Livewire is loading to prevent flicker --}}
            <div wire:loading.remove class="card position-absolute mt-1 border-0" style="z-index: 2;left: 0;right: 0;">
                <div class="card-body shadow">
                    <div class="alert alert-warning mb-0">
                        No products found matching your filters.
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
