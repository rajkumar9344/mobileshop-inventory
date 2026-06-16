@extends('layouts.app')

@section('title', 'Edit Purchase Return')

@section('breadcrumb')
    @php $isReadOnly = !empty($readonly); @endphp
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-returns.index') }}">Purchase Returns</a></li>
        <li class="breadcrumb-item active">{{ $isReadOnly ? 'View' : 'Edit' }}</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <!-- Search will be placed above the product table card (same as Sales) -->

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')
                        @php $isReadOnly = !empty($readonly); @endphp
                        <form id="purchase-return-form" action="{{ $isReadOnly ? '#' : route('purchase-returns.update', $purchase_return) }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required value="{{ $purchase_return->reference }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="date">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" {{ $isReadOnly ? 'disabled' : 'required' }} value="{{ $purchase_return->date }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                            <select class="form-control select2-supplier" name="supplier_id" id="supplier_id" {{ $isReadOnly ? 'disabled' : 'required' }}>
                                                <option value="{{ $purchase_return->supplier_id }}" selected>{{ $purchase_return->supplier_name }}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="area">Area</label>
                                        <input type="text" class="form-control" name="area" id="area" maxlength="30" readonly placeholder="Area" value="{{ old('area', $purchase_return->area ?? '') }}">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="balance">Open Balance</label>
                                        <input type="text" class="form-control" name="balance" id="balance" maxlength="15" pattern="^\d+(\.\d{1,2})?$|^\d+(,\d{1,2})?$" readonly placeholder="0.00" value="{{ old('balance', $purchase_return->balance ?? '0.00') }}">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="bill_balance_display">Bill Balance</label>
                                        <input type="text" class="form-control" id="bill_balance_display" readonly value="{{ $purchase_return->supplier_id ? number_format($purchase_return->bill_balance_before ?? (optional($purchase_return->supplier)->bill_balance ?? 0), 2, '.', '') : '0.00' }}">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="total_balance_display">Total Balance</label>
                                        <input type="text" class="form-control" id="total_balance_display" readonly value="{{ $purchase_return->supplier_id ? number_format(((float)($purchase_return->balance ?? 0)) + ((float)($purchase_return->bill_balance_before ?? (optional($purchase_return->supplier)->bill_balance ?? 0))), 2, '.', '') : '0.00' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row  mb-3">
                                <div class="col-md-3 pr-1">
                                    <label for="invoice_no" class="mb-1">Invoice No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="invoice_no" id="invoice_no" maxlength="20" {{ $isReadOnly ? 'disabled' : 'required' }} placeholder="Invoice Number" value="{{ old('invoice_no', $purchase_return->invoice_no ?? '') }}">
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="invoice_date" class="mb-1">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" {{ $isReadOnly ? 'disabled' : 'required' }} value="{{ old('invoice_date', $purchase_return->invoice_date ?? now()->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="excess_amount" class="mb-1">Excess</label>
                                    <input type="text" class="form-control" name="excess_amount" id="excess_amount" maxlength="15" readonly placeholder="0.00" value="{{ old('excess_amount', $purchase_return->excess_amount ?? '0.00') }}">
                                </div>
                            </div>

                            @unless($isReadOnly)
                                <div class="mb-3">
                                    <livewire:search-product :context="'purchase_return'"/>
                                </div>
                            @endunless

                            <livewire:product-cart :cartInstance="$cartInstance ?? 'purchase_return'" :data="$purchase_return" :readonly="$isReadOnly"/>

                            <!-- Hidden required calculation fields (populated from ProductCart display) -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="{{ $purchase_return->tax_percentage ?? 0 }}">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="{{ $purchase_return->discount_percentage ?? 0 }}">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="{{ $purchase_return->shipping_amount ?? 0 }}">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="{{ $purchase_return->total_amount ?? 0 }}">

                            <!-- overall calculation fields mirrored from the cart display -->
                            <input type="hidden" name="overall_nos" id="hidden_overall_nos" value="{{ $purchase_return->overall_nos ?? 0 }}">
                            <input type="hidden" name="overall_quantity" id="hidden_overall_quantity" value="{{ $purchase_return->overall_quantity ?? 0 }}">
                            <input type="hidden" name="overall_gross_amount" id="hidden_overall_gross_amount" value="{{ $purchase_return->overall_gross_amount ?? 0 }}">
                            <input type="hidden" name="overall_taxable_amount" id="hidden_overall_taxable_amount" value="{{ $purchase_return->overall_taxable_amount ?? 0 }}">
                            <input type="hidden" name="overall_tax_amount" id="hidden_overall_tax_amount" value="{{ $purchase_return->overall_tax_amount ?? 0 }}">
                            <input type="hidden" name="overall_amount" id="hidden_overall_amount" value="{{ $purchase_return->overall_amount ?? 0 }}">



                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>{{ $purchase_return->note }}</textarea>
                            </div>

                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="create_receipt" name="create_receipt" value="1" {{ $purchase_return->create_receipt ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                <label class="form-check-label" for="create_receipt">Create Purchases Receipt (lineless)</label>
                            </div>

                            <div class="mt-3 d-flex justify-content-end">
                                <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary mr-2">Back</a>
                                @unless($isReadOnly)
                                    <button type="submit" class="btn btn-primary">
                                        Update Purchase Return <i class="bi bi-check"></i>
                                    </button>
                                @endunless
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset_v('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 for supplier dropdown
            $('#supplier_id').select2({
                placeholder: 'Select a supplier',
                allowClear: true,
                ajax: {
                    url: '{{ route("api.suppliers.search") }}',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            q: params.term || '', // Allow empty search
                            page: params.page
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results,
                            pagination: {
                                more: (params.page * 30) < data.results.length
                            }
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0, // Allow searching with no input
                escapeMarkup: function (markup) {
                    return markup;
                },
                templateResult: function (supplier) {
                    if (supplier.loading) return supplier.text;
                    return supplier.text;
                },
                templateSelection: function (supplier) {
                    return supplier.text || supplier.supplier_name;
                }
            });

            // Set initial value for Select2 (do not trigger change – we already rendered area/balance/excess server-side)
            var initialSupplierId = '{{ $purchase_return->supplier_id }}';
            var initialSupplierName = '{{ $purchase_return->supplier_name }}';
            if (initialSupplierId && initialSupplierName) {
                var option = new Option(initialSupplierName, initialSupplierId, true, true);
                $('#supplier_id').append(option);
            }

            // Single supplier-change pipeline: update supplier fields and apply supplier default discount.
            $('#supplier_id').on('change', function() {
                var supplierId = $(this).val();
                var hiddenInput = document.getElementById('product-cart-additional-discount');

                if (!supplierId) {
                    $('#area').val('');
                    $('#balance').val('0.00');
                    $('#excess_amount').val('0.00');
                    if (window.Livewire && typeof Livewire.emit === 'function') {
                        Livewire.emit('applyCustomerAdditionalDiscount', { discount: 0 });
                    }
                    if (hiddenInput) {
                        hiddenInput.value = 0;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                    return;
                }

                $.get('/api/suppliers/' + supplierId).done(function(res) {
                    $('#area').val(res.area || '');
                    $('#balance').val(parseFloat(res.open_balance || 0).toFixed(2));
                    $('#bill_balance_display').val(res.bill_balance_formatted !== undefined ? res.bill_balance_formatted : '0.00');
                    $('#total_balance_display').val(res.total_balance_formatted !== undefined ? res.total_balance_formatted : '0.00');
                    $('#excess_amount').val(parseFloat(res.excess_amount || 0).toFixed(2));

                    var lessDisc = parseFloat(res.less_discount_percent || 0) || 0;
                    if (window.Livewire && typeof Livewire.emit === 'function') {
                        Livewire.emit('applyCustomerAdditionalDiscount', { discount: lessDisc });
                    }
                    if (hiddenInput) {
                        hiddenInput.value = lessDisc;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }).fail(function() {
                    $('#area').val('');
                    $('#balance').val('0.00');
                    $('#excess_amount').val('0.00');
                    if (window.Livewire && typeof Livewire.emit === 'function') {
                        Livewire.emit('applyCustomerAdditionalDiscount', { discount: 0 });
                    }
                    if (hiddenInput) {
                        hiddenInput.value = 0;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            });

            // no initial AJAX load: keep server-side balance/area/excess intact
            // user selection will trigger loadSupplierData via select2 handler above

            // Edit mode: set supplier default only as new-line default on initial load.
            // Do not emit applyCustomerAdditionalDiscount here, to avoid overwriting saved row discounts.
            (function setInitialSupplierDiscountDefaultOnly() {
                try {
                    var initialSupplier = $('#supplier_id').val();
                    var hiddenInput = document.getElementById('product-cart-additional-discount');
                    if (!hiddenInput) return;

                    if (!initialSupplier) {
                        hiddenInput.value = 0;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        return;
                    }

                    $.get('/api/suppliers/' + initialSupplier).done(function(res) {
                        var lessDisc = parseFloat(res.less_discount_percent || 0) || 0;
                        hiddenInput.value = lessDisc;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }).fail(function() {
                        hiddenInput.value = 0;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                } catch (e) { console.debug('setInitialSupplierDiscountDefaultOnly error', e); }
            })();

            // Ensure hidden calculation fields reflect the current cart display before submit
            $('#purchase-return-form').submit(function (e) {
                // Prevent duplicate submits: if form already submitted, stop.
                if (this.dataset.submitted === 'true') {
                    e.preventDefault();
                    return false;
                }

                if (typeof updateHiddenFields === 'function') updateHiddenFields();

                // Mark as submitted and disable the submit button to prevent multiple clicks
                this.dataset.submitted = 'true';
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    try {
                        btn.disabled = true;
                        btn.dataset.originalHtml = btn.innerHTML;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                    } catch (err) {}
                }
            });

            // Observe Livewire cart changes and update hidden fields when DOM changes
            const cartContainer = document.querySelector('.table-responsive');
            if (cartContainer && window.MutationObserver) {
                const observer = new MutationObserver(function () {
                    if (typeof updateHiddenFields === 'function') updateHiddenFields();
                });
                observer.observe(cartContainer, { childList: true, subtree: true, characterData: true });
            }

            // Initial population of hidden fields
            if (typeof updateHiddenFields === 'function') updateHiddenFields();

            // Disable submit when cart invalid — robust handlers + DOM fallback
            function setSubmitDisabled(disabled) {
                const form = document.getElementById('purchase-return-form');
                if (!form) return;
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = disabled;
            }

            // Ensure submit is enabled by default until we receive a validity payload
            setSubmitDisabled(false);

            if (window.Livewire) {
                Livewire.on('cart-validity', function(payload) {
                    // Only act on well-formed payloads to avoid accidental disables
                    if (payload && typeof payload.valid !== 'undefined') {
                        setSubmitDisabled(!payload.valid);
                    }
                });
            } else {
                document.addEventListener('livewire:load', function () {
                    Livewire.on('cart-validity', function(payload) {
                        if (payload && typeof payload.valid !== 'undefined') {
                            setSubmitDisabled(!payload.valid);
                        }
                    });
                });
            }

            // Also listen for browser-dispatched events (dispatchBrowserEvent)
            window.addEventListener('cart-validity', function(e) {
                var payload = (e && e.detail) ? e.detail : {};
                if (payload && typeof payload.valid !== 'undefined') {
                    setSubmitDisabled(!payload.valid);
                }
            });

            // Fallback: infer validity from DOM highlight state. Debounced to avoid thrash.
            var _submitTimer = null;
            function updateSubmitFromDOM() {
                if (_submitTimer) clearTimeout(_submitTimer);
                _submitTimer = setTimeout(function(){
                    var invalidRow = document.querySelector('tr.invalid-row, tr[style*="#f8d7da"]');
                    setSubmitDisabled(!!invalidRow);
                }, 60);
            }

            // Watch for DOM mutations under the cart table to update submit state
            var cartContainerEl = document.querySelector('.table-responsive');
            if (cartContainerEl && window.MutationObserver) {
                var mo = new MutationObserver(function(){ updateSubmitFromDOM(); });
                mo.observe(cartContainerEl, { childList: true, subtree: true, attributes: true });
            }

            // Also update on typical Livewire lifecycle hooks
            document.addEventListener('livewire:load', updateSubmitFromDOM);
            document.addEventListener('livewire:update', updateSubmitFromDOM);
            if (window.Livewire && Livewire.hook) {
                try { Livewire.hook('message.processed', updateSubmitFromDOM); } catch(e){}
            }

            // run once immediately to set initial state
            updateSubmitFromDOM();
        });

        // Function to update hidden fields with current values from the cart display
        function updateHiddenFields() {
            // Default percentages/amounts
            const taxEl = document.getElementById('hidden_tax_percentage');
            if (taxEl) taxEl.value = taxEl.value || '0';
            const discEl = document.getElementById('hidden_discount_percentage');
            if (discEl) discEl.value = discEl.value || '0';
            const shipEl = document.getElementById('hidden_shipping_amount');
            if (shipEl) shipEl.value = shipEl.value || '0';

            // Prefer the Overall Amount field from the ProductCart Livewire component if present
            const overallNetEl = document.getElementById('overall_amount');
            let cartTotal = '0';
            if (overallNetEl) {
                cartTotal = overallNetEl.value || overallNetEl.textContent || '0';
            } else {
                // Fallback to last table totals cell if present
                const cartTotalElement = document.querySelector('.table-responsive .table tr:last-child th:last-child, .table-responsive .table tr:last-child td:last-child');
                cartTotal = cartTotalElement ? cartTotalElement.textContent : '0';
            }
            cartTotal = String(cartTotal).replace(/[^0-9\.\-]/g, '') || '0';
            const hiddenTotal = document.getElementById('hidden_total_amount');
            if (hiddenTotal) hiddenTotal.value = cartTotal;

            // Collect per-item Rate before Discount values and add them as submitted_rates[...] hidden inputs
            try {
                document.querySelectorAll('tr[id^="cart-row-"]').forEach(function(row) {
                    const productId = row.dataset.productId;
                    if (!productId) return;
                    // Hidden field created by x-currency-input for the rate uses id 'rate_<id>_raw'
                    const rawHidden = document.getElementById('rate_' + productId + '_raw');
                    let rateVal = null;
                    if (rawHidden && rawHidden.value !== undefined) {
                        rateVal = rawHidden.value; // formatted with 2 decimals (major units)
                    } else {
                        // Fallback: find visible input with id 'rate_<id>' and read its value
                        const vis = document.getElementById('rate_' + productId);
                        if (vis) rateVal = String(vis.value || vis.getAttribute('value') || '').replace(/[^0-9\.\-]/g, '') || null;
                    }

                    if (rateVal !== null) {
                        let existing = document.querySelector('input[name="submitted_rates[' + productId + ']"]');
                        if (!existing) {
                            existing = document.createElement('input');
                            existing.type = 'hidden';
                            existing.name = 'submitted_rates[' + productId + ']';
                            document.getElementById('purchase-return-form').appendChild(existing);
                        }
                        existing.value = rateVal;
                    }
                });
            } catch (e) { /* ignore */ }

            const fields = ['nos','quantity','gross_amount','taxable_amount','cgst','sgst','igst','tax_amount','tcs_percent','amount','other','adj','net_rate'];
            fields.forEach(function(key) {
                const src = document.getElementById('overall_' + key);
                const dest = document.getElementById('hidden_overall_' + key);
                if (dest) {
                    if (src) {
                        dest.value = (src.value !== undefined && src.value !== null) ? src.value : (src.textContent || '0');
                    } else {
                        // preserve existing model value if present, otherwise set 0
                        dest.value = dest.value || '0';
                    }
                }
            });
        }

    </script>
@endpush
