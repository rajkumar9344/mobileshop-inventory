@extends('layouts.app')

@section('title', 'Create Purchase Return')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-returns.index') }}">Purchase Returns</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')
                        <form id="purchase-return-form" action="{{ route('purchase-returns.store') }}" method="POST">
                            @csrf
                            <div class="form-row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required readonly value="PRRN">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="date">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                            <select class="form-control select2-supplier" name="supplier_id" id="supplier_id" required>
                                                <option value="">-- Select supplier --</option>
                                                @foreach(\Modules\People\Entities\Supplier::where('status', 'active')->orderBy('supplier_name', 'asc')->get() as $supplier)
                                                    <option value="{{ $supplier->id }}" data-area="{{ $supplier->area }}" data-balance="{{ $supplier->open_balance ?? 0 }}" data-excess="{{ $supplier->excess_amount ?? 0 }}">{{ $supplier->supplier_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="area">Area</label>
                                        <input type="text" class="form-control" name="area" id="area" maxlength="30" readonly placeholder="Area">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <input type="text" class="form-control" name="balance" id="balance" maxlength="15" pattern="^\d+(\.\d{1,2})?$|^\d+(,\d{1,2})?$" readonly placeholder="0.00" value="0.00">
                                    </div>
                                </div>
                            </div>
                            <div class="form-row  mb-3">
                                <div class="col-md-3 pr-1">
                                    <label for="invoice_no" class="mb-1">Invoice No <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="invoice_no" id="invoice_no" maxlength="20" required placeholder="Invoice Number">
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="invoice_date" class="mb-1">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" required value="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="excess_amount" class="mb-1">Excess</label>
                                    <input type="text" class="form-control" name="excess_amount" id="excess_amount" maxlength="15" readonly placeholder="0.00" value="0.00">
                                </div>
                            </div>

                            <div class="mb-3">
                                <livewire:search-product :context="'purchase_return'"/>
                            </div>

                            <livewire:product-cart :cartInstance="'purchase_return'"/>

                            <!-- Hidden required calculation fields (populated from ProductCart display) -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="0">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="0">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="0">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">
                            <!-- overall calculation fields mirrored from the cart display -->
                            <input type="hidden" name="overall_nos" id="hidden_overall_nos">
                            <input type="hidden" name="overall_quantity" id="hidden_overall_quantity">
                            <input type="hidden" name="overall_gross_amount" id="hidden_overall_gross_amount">
                            <input type="hidden" name="overall_taxable_amount" id="hidden_overall_taxable_amount">
                            <input type="hidden" name="overall_tax_amount" id="hidden_overall_tax_amount">
                            <input type="hidden" name="overall_amount" id="hidden_overall_amount">



                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                            </div>

                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="create_receipt" name="create_receipt" value="1">
                                <label class="form-check-label" for="create_receipt">Create Purchases Receipt (lineless)</label>
                            </div>

                            <div class="mt-3 d-flex justify-content-end">
                                <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary mr-2">Back</a>
                                <button type="submit" class="btn btn-primary">
                                    Create Purchase Return <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 for supplier dropdown
            $('#supplier_id').select2({
                placeholder: 'Select a supplier',
                allowClear: true
            });

            // Handle supplier selection
            $('#supplier_id').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var area = selectedOption.data('area') || '';
                var balance = selectedOption.data('balance') || 0;
                var excess = selectedOption.data('excess') || 0;

                $('#area').val(area);
                $('#balance').val(parseFloat(balance).toFixed(2));
                $('#excess_amount').val(parseFloat(excess).toFixed(2));

                // Fetch supplier JSON to get discount fields and apply them to the Livewire cart
                var id = $(this).val();
                if (!id) {
                    // Clear any supplier-applied discount
                    if (window.Livewire && typeof Livewire.emit === 'function') {
                        Livewire.emit('applyCustomerAdditionalDiscount', { discount: 0 });
                    }
                    return;
                }

                $.get('/api/suppliers/' + id).done(function(res) {
                    var lessDisc = parseFloat(res.less_discount_percent || 0) || 0;
                    if (window.Livewire && typeof Livewire.emit === 'function') {
                        Livewire.emit('applyCustomerAdditionalDiscount', { discount: lessDisc });
                    }

                    // Also set the hidden input inside product-cart so wire:model updates reliably
                    var hiddenInput = document.getElementById('product-cart-additional-discount');
                    if (hiddenInput) {
                        hiddenInput.value = lessDisc;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }).fail(function() {
                    // On failure, clear any supplier-applied discount
                    if (window.Livewire && typeof Livewire.emit === 'function') {
                        Livewire.emit('applyCustomerAdditionalDiscount', { discount: 0 });
                    }
                    var hiddenInput = document.getElementById('product-cart-additional-discount');
                    if (hiddenInput) {
                        hiddenInput.value = 0;
                        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            });

            // Apply supplier default discount on initial load if supplier is preselected
            (function applyInitialSupplierDiscount() {
                try {
                    var initialSupplier = $('#supplier_id').val();
                    if (!initialSupplier) return;
                    $.get('/api/suppliers/' + initialSupplier).done(function(res) {
                        var lessDisc = parseFloat(res.less_discount_percent || 0) || 0;
                        if (window.Livewire && typeof Livewire.emit === 'function') {
                            Livewire.emit('applyCustomerAdditionalDiscount', { discount: lessDisc });
                        }
                        var hiddenInput = document.getElementById('product-cart-additional-discount');
                        if (hiddenInput) {
                            hiddenInput.value = lessDisc;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }).fail(function() {
                        if (window.Livewire && typeof Livewire.emit === 'function') {
                            Livewire.emit('applyCustomerAdditionalDiscount', { discount: 0 });
                        }
                        var hiddenInput = document.getElementById('product-cart-additional-discount');
                        if (hiddenInput) {
                            hiddenInput.value = 0;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                } catch (e) { console.debug('applyInitialSupplierDiscount error', e); }
            })();

            $('#purchase-return-form').submit(function (e) {
                // Prevent duplicate submits: if form already submitted, stop.
                if (this.dataset.submitted === 'true') {
                    e.preventDefault();
                    return false;
                }

                // Ensure hidden calculation fields reflect the current cart display
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

            // Watch cart for changes and refresh hidden fields (in case Livewire updates the table)
            const cartContainer = document.querySelector('.table-responsive');
            if (cartContainer && window.MutationObserver) {
                const observer = new MutationObserver(function () {
                    if (typeof updateHiddenFields === 'function') updateHiddenFields();
                });
                observer.observe(cartContainer, { childList: true, subtree: true, characterData: true });
            }

            // Disable submit when cart invalid
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
                    // Only act when the payload explicitly contains a boolean `valid` property.
                    // Ignore other cart-validity events that don't convey validity to avoid
                    // accidentally disabling the submit button due to a loosely-shaped event.
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
            // Some Livewire code may use `dispatchBrowserEvent('cart-validity', payload)`;
            // that emits a DOM CustomEvent with the payload in `event.detail`.
            window.addEventListener('cart-validity', function(e) {
                var payload = (e && e.detail) ? e.detail : {};
                if (payload && typeof payload.valid !== 'undefined') {
                    setSubmitDisabled(!payload.valid);
                }
            });

            // Fallback: infer validity from DOM highlight state. This ensures the submit
            // button follows the visual highlighted rows even if an event was missed.
            var _submitTimer = null;
            function updateSubmitFromDOM() {
                if (_submitTimer) clearTimeout(_submitTimer);
                _submitTimer = setTimeout(function(){
                    // look for rows with explicit invalid-row class or inline background color
                    var invalidRow = document.querySelector('tr.invalid-row, tr[style*="#f8d7da"]');
                    setSubmitDisabled(!!invalidRow);
                }, 60); // debounce briefly to avoid thrashing during Livewire morphs
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
            if (taxEl) taxEl.value = '0';
            const discEl = document.getElementById('hidden_discount_percentage');
            if (discEl) discEl.value = '0';
            const shipEl = document.getElementById('hidden_shipping_amount');
            if (shipEl) shipEl.value = '0';

            // Prefer the Overall Amount field from the ProductCart Livewire component if present
            const overallNetEl = document.getElementById('overall_amount');
            let cartTotal = '0';
            if (overallNetEl) {
                cartTotal = overallNetEl.value || overallNetEl.textContent || '0';
            } else {
                // Fallback to last table totals cell if present
                const cartTotalElement = document.querySelector('.table-responsive .table .table-striped tr:last-child th:last-child');
                cartTotal = cartTotalElement ? cartTotalElement.textContent : '0';
            }
            cartTotal = String(cartTotal).replace(/[^0-9\.\-]/g, '') || '0';
            const hiddenTotal = document.getElementById('hidden_total_amount');
            if (hiddenTotal) hiddenTotal.value = cartTotal;

            const fields = ['nos','quantity','gross_amount','taxable_amount','cgst','sgst','igst','tax_amount','tcs_percent','amount','other','adj','net_rate'];
            fields.forEach(function(key) {
                const src = document.getElementById('overall_' + key);
                const dest = document.getElementById('hidden_overall_' + key);
                if (dest) {
                    if (src) {
                        dest.value = (src.value !== undefined && src.value !== null) ? src.value : (src.textContent || '0');
                    } else {
                        dest.value = '0';
                    }
                }
            });

            // Collect per-item Rate before Discount values and add them as submitted_rates[...] hidden inputs
            try {
                document.querySelectorAll('tr[id^="cart-row-"]').forEach(function(row) {
                    const productId = row.dataset.productId;
                    if (!productId) return;
                    const rawHidden = document.getElementById('rate_' + productId + '_raw');
                    let rateVal = null;
                    if (rawHidden && rawHidden.value !== undefined) {
                        rateVal = rawHidden.value;
                    } else {
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
        }
    </script>
@endpush
