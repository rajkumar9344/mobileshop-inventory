<div>
    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </div>
    @endif
    <div class="table-responsive">
        <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center" style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <table class="table table-bordered">
            <thead>
            <tr class="align-middle">
                <th class="align-middle">#</th>
                <th class="align-middle">Product Name</th>
                <th class="align-middle">Code</th>
                <th class="align-middle text-center">Unit</th>
                <th class="align-middle text-center">Open Qty (Current)</th>
                <th class="align-middle">Quantity</th>
                <th class="align-middle text-center">Open Qty (After Adj)</th>
                <th class="align-middle">Type</th>
                <th class="align-middle">Action</th>
            </tr>
            </thead>
            <tbody>
            @if(!empty($products))
                @foreach($products as $key => $product)
                    <tr>
                        <td class="align-middle">{{ $key + 1 }}</td>
                        <td class="align-middle">{{ $product['product_name'] ?? $product['product']['product_name'] }}</td>
                        <td class="align-middle">
                            @if(!empty($product['product_codes']) && is_array($product['product_codes']))
                                {{ implode(', ', array_unique($product['product_codes'])) }}
                            @elseif(!empty($product['product']['id']))
                                @php
                                    $__codes = \Modules\Product\Entities\ProductCode::where('product_id', $product['product']['id'])
                                        ->orderByDesc('is_primary')
                                        ->pluck('code')
                                        ->toArray();
                                @endphp
                                {{ implode(', ', $__codes) }}
                            @else
                                {{ $product['product_code'] ?? ($product['product']['product_code'] ?? '') }}
                            @endif
                        </td>
                            @php
                                // Use stored snapshots if present (for edit), otherwise prefer nested product 'open_quantity',
                                // then top-level open_quantity, then product_quantity.
                                $qtyDefault = data_get($product, 'quantity', 1);
                                $typeDefault = data_get($product, 'type', 'add');

                                if (!is_null(data_get($product, 'open_now'))) {
                                    $openNow = (int) data_get($product, 'open_now');
                                } elseif (!is_null(data_get($product, 'product.open_quantity'))) {
                                    $openNow = (int) data_get($product, 'product.open_quantity');
                                } elseif (!is_null(data_get($product, 'open_quantity'))) {
                                    $openNow = (int) data_get($product, 'open_quantity');
                                } else {
                                    $openNow = (int) data_get($product, 'product_quantity', 0);
                                }

                                if (!is_null(data_get($product, 'open_after'))) {
                                    $openAfter = (int) data_get($product, 'open_after');
                                } else {
                                    $openAfter = ($typeDefault === 'sub') ? ($openNow - $qtyDefault) : ($openNow + $qtyDefault);
                                }

                                // Unit from nested product preferred
                                if (!is_null(data_get($product, 'product.product_unit'))) {
                                    $unit = data_get($product, 'product.product_unit');
                                } else {
                                    $unit = data_get($product, 'product_unit', '');
                                }
                            @endphp

                        <td class="align-middle text-center">{{ $unit }}</td>

                        <td class="align-middle text-center">
                            <span class="badge badge-info open-now" data-row="{{ $key }}" data-open-now="{{ $openNow }}">{{ $openNow }}</span>
                        </td>

                        <input type="hidden" name="product_ids[{{ $key }}]" value="{{ $product['product']['id'] ?? $product['id'] }}">

                        <td class="align-middle">
                            <input type="number" name="quantities[{{ $key }}]" min="1" max="99999" required step="1" inputmode="numeric" class="form-control adj-quantity" data-row="{{ $key }}" value="{{ $qtyDefault }}" oninput="this.value = this.value.toString().replace(/[^0-9]/g,'').slice(0,5)">
                            <div class="invalid-feedback">Please enter a quantity of 1 or more.</div>
                        </td>

                        <td class="align-middle text-center">
                            <span class="badge badge-secondary open-after" data-row="{{ $key }}">{{ $openAfter }}</span>
                        </td>
                        <td class="align-middle">
                            @if(isset($product['type']))
                                @if($product['type'] == 'add')
                                    <select name="types[{{ $key }}]" class="form-control adj-type" data-row="{{ $key }}">
                                        <option value="add" selected>+</option>
                                        <option value="sub">-</option>
                                    </select>
                                @elseif($product['type'] == 'sub')
                                    <select name="types[{{ $key }}]" class="form-control adj-type" data-row="{{ $key }}">
                                        <option value="sub" selected>-</option>
                                        <option value="add">+</option>
                                    </select>
                                @endif
                            @else
                                <select name="types[{{ $key }}]" class="form-control adj-type" data-row="{{ $key }}">
                                    <option value="add">+</option>
                                    <option value="sub">-</option>
                                </select>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-danger" wire:click="removeProduct({{ $key }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
                @else
                <tr>
                    <td colspan="9" class="text-center">
                        <span class="text-danger">
                            Please search & select products!
                        </span>
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
    <style>
        /* Remove spin buttons on number inputs for quantity */
        .adj-quantity::-webkit-outer-spin-button,
        .adj-quantity::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .adj-quantity {
            -moz-appearance: textfield;
        }

        /* Highlight rows where after-quantity is negative */
        .negative-row {
            background-color: #f8d7da !important;
        }
        .negative-row .open-after {
            color: #842029;
            font-weight: 700;
        }
    </style>
    <script>
        (function(){
            function recalc(row){
                var openNowEl = document.querySelector('.open-now[data-row="'+row+'"]');
                if(!openNowEl) return;
                var openNow = parseInt(openNowEl.dataset.openNow) || 0;
                var qtyEl = document.querySelector('.adj-quantity[data-row="'+row+'"]');
                var qty = qtyEl ? (parseInt(qtyEl.value) || 0) : 0;
                var typeEl = document.querySelector('.adj-type[data-row="'+row+'"]');
                var type = typeEl ? typeEl.value : 'add';
                var after = (type === 'sub') ? (openNow - qty) : (openNow + qty);
                var afterEl = document.querySelector('.open-after[data-row="'+row+'"]');
                if(afterEl) afterEl.textContent = after;

                // Immediately check for negatives and update UI
                checkForNegatives();
            }

            function checkForNegatives(){
                var btn = document.getElementById('create-adjustment-btn');
                var warn = document.getElementById('adj-warning');
                var anyNegative = false;

                document.querySelectorAll('.open-after').forEach(function(el){
                    var n = parseInt(el.textContent) || 0;
                    var tr = el.closest('tr');
                    if(n < 0){
                        anyNegative = true;
                        if(tr) tr.classList.add('table-danger','negative-row');
                        el.setAttribute('title', 'Open Qty (After Adj) would be negative');
                    } else {
                        if(tr) tr.classList.remove('table-danger','negative-row');
                        el.removeAttribute('title');
                    }
                });

                if(anyNegative){
                    if(btn) btn.disabled = true;
                    if(warn) warn.style.display = 'block';
                } else {
                    if(btn) btn.disabled = false;
                    if(warn) warn.style.display = 'none';
                }
            }

            // delegated handlers work when Livewire replaces DOM
            document.addEventListener('input', function(e){
                if(e.target && e.target.matches('.adj-quantity')){
                    var row = e.target.dataset.row;
                    recalc(row);
                }
            });

            document.addEventListener('change', function(e){
                if(e.target && e.target.matches('.adj-type')){
                    var row = e.target.dataset.row;
                    recalc(row);
                }
            });

            // initial calculation for existing rows
            function runInitial(){
                document.querySelectorAll('.open-now[data-row]').forEach(function(el){
                    var row = el.dataset.row;
                    recalc(row);
                });
            }

            // Run on load and also after Livewire DOM updates if present
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', runInitial);
            } else {
                runInitial();
            }

            if (window.Livewire && typeof window.Livewire.on === 'function') {
                window.Livewire.hook('message.processed', function () { runInitial(); });
            }
        })();
    </script>
</div>
