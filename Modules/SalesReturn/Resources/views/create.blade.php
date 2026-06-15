@extends('layouts.app')

@section('title', 'Create Sale Return')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-returns.index') }}">Sale Returns</a></li>
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
                        <form id="sale-return-form" action="{{ route('sale-returns.store') }}" method="POST">
                            @csrf

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required readonly value="SLRN">
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                            <select class="form-control" name="customer_id" id="customer_id" required>
                                                <option value="">-- Select customer --</option>
                                                @php
                                                    $selectedCustomer = old('customer_id') ?? request()->get('customer_id');
                                                @endphp
                                                @foreach(\Modules\People\Entities\Customer::where('is_active', true)->orderBy('customer_name', 'asc')->get() as $customer)
                                                    <option value="{{ $customer->id }}" {{ ((string)$selectedCustomer === (string)$customer->id) ? 'selected' : '' }}>{{ $customer->customer_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="date">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row mb-3">
                                <div class="col-md-3 pr-1">
                                    <label for="area" class="mb-1">Area</label>
                                    <input type="text" class="form-control" name="area" id="area" maxlength="30" placeholder="Area">
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="phone" class="mb-1">Phone No</label>
                                    <input type="tel" class="form-control" name="phone" id="phone" maxlength="15" pattern="\+?[0-9]{7,15}" title="Only numbers and + (UAE contact number, max 15)" oninput="validatePhone(this)" placeholder="+971501234567">
                                    <small id="phone-error" class="text-danger" style="display: none;"></small>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="opening_balance" class="mb-1">Balance</label>
                                    <input type="text" class="form-control" name="opening_balance" id="opening_balance" maxlength="15" pattern="^-?\d+(?:\.\d{1,2})?$|^-?\d+(?:,\d{1,2})?$" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.\-]/g,'').replace(/(?!^)-/g,'').slice(0,15)" readonly>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="excess_amount_display" class="mb-1">Excess Amount</label>
                                    <input type="text" class="form-control" id="excess_amount_display" readonly value="0.00">
                                    <input type="hidden" name="excess_amount" id="excess_amount" value="0.00">
                                </div>
                            </div>

                            {{-- Product search placed directly above the product cart for easier access --}}
                            <div class="mb-3">
                                <livewire:search-product :context="'sale_return'"/>
                            </div>

                            <livewire:product-cart :cartInstance="'sale_return'"/>

                            <!-- Hidden required calculation fields (sale module behaviour mirrored) -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="0">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="0">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="0">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="0">
                            <!-- Keep status hidden to match sale validation expectations -->
                            <input type="hidden" name="status" value="Pending">

                            <!-- Hidden fields for Overall Calculations (populated from ProductCart display) -->
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
                                <input type="checkbox" class="form-check-input" id="create_receipt" name="create_receipt" value="1" {{ old('create_receipt') ? 'checked' : '' }}>
                                <label class="form-check-label" for="create_receipt">Create receipt for this sale return</label>
                                <small class="form-text text-muted">If checked, a linked receipt will be created using the overall net amount.</small>
                            </div>

                            <div class="mt-3 d-flex justify-content-end">
                                <a href="{{ route('sale-returns.index') }}" class="btn btn-secondary mr-2">Back</a>
                                <button type="submit" class="btn btn-primary">
                                    Create Sale Return <i class="bi bi-check"></i>
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
    <script src="{{ asset('js/jquery-mask-money.js') }}"></script>
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Populate hidden calculation fields on load and before submit
            updateHiddenFields();

            // Ensure hidden fields are up-to-date before submit
            $('#sale-return-form').submit(function () {
                updateHiddenFields();
            });

            // Watch product cart area for DOM changes and refresh hidden fields
            const cartContainer = document.querySelector('.table-responsive');
            if (cartContainer && window.MutationObserver) {
                const observer = new MutationObserver(function () {
                    updateHiddenFields();
                });
                observer.observe(cartContainer, { childList: true, subtree: true, characterData: true });
            }
        });

        // Function to update hidden fields with current values from the cart display
        function updateHiddenFields() {
            // Default percentages/amounts
            document.getElementById('hidden_tax_percentage').value = '0';
            document.getElementById('hidden_discount_percentage').value = '0';
            document.getElementById('hidden_shipping_amount').value = '0';

            // Prefer the Overall Amount field from the ProductCart Livewire component if present
            const overallNetEl = document.getElementById('overall_amount');
            let cartTotal = '0';
            if (overallNetEl) {
                cartTotal = overallNetEl.value || overallNetEl.textContent || '0';
            } else {
                // Fallback: try to read from cart table last row (older markup)
                const cartTotalElement = document.querySelector('.table-responsive .table .table-striped tr:last-child th:last-child');
                cartTotal = cartTotalElement ? cartTotalElement.textContent : '0';
            }
            // Strip currency symbols and thousands separators, keep minus/decimal
            cartTotal = String(cartTotal).replace(/[^0-9\.\-]/g, '') || '0';
            document.getElementById('hidden_total_amount').value = cartTotal;

            // Mirror overall calculation display inputs (if present) into hidden inputs
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
        }

        // Expose for other scripts/components
        window.updateHiddenFields = updateHiddenFields;
    </script>
        <script>
            // Customer select should populate address, phone, balance and excess.
            // The customer -> product-cart discount sync (and Livewire emits)
            // is intentionally disabled below by commenting only that portion.
            $(document).ready(function () {
                $('#customer_id').on('change', function () {
                    var id = $(this).val();
                    if (!id) return;
                    var url = '/customers/' + id + '/json';
                    $.get(url).done(function (res) {
                        // Discount sync intentionally disabled:
                        /*
                        var hiddenInput = document.getElementById('product-cart-additional-discount');
                        if (hiddenInput) {
                            var val = 0;
                            if (res && (res.additional_discount !== undefined && res.additional_discount !== null)) {
                                val = res.additional_discount;
                            } else if (res && (res.cash_discount !== undefined && res.cash_discount !== null)) {
                                val = res.cash_discount;
                            }
                            hiddenInput.value = val;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (window.Livewire && typeof Livewire.emit === 'function') {
                            Livewire.emit('applyCustomerAdditionalDiscount', { discount: (res && res.additional_discount) ? res.additional_discount : 0 });
                            Livewire.emit('applyCustomerCashDiscount', { percent: (res && res.cash_discount) ? res.cash_discount : 0 });
                        }
                        */

                        $('#area').val(res.area || '');
                        $('#phone').val(res.customer_phone || '');
                        $('#opening_balance').val(res.opening_balance !== undefined ? res.opening_balance : '0.00');
                        if (res.excess_amount !== undefined) {
                            $('#excess_amount_display').val(res.excess_amount);
                            $('#excess_amount').val(res.excess_amount);
                        } else {
                            $('#excess_amount_display').val('0.00');
                            $('#excess_amount').val('0.00');
                        }

                        if (window.updateHiddenFields) window.updateHiddenFields();
                    }).fail(function () {
                        // Reset discount sync is also intentionally disabled:
                        /*
                        var hiddenInput = document.getElementById('product-cart-additional-discount');
                        if (hiddenInput) {
                            hiddenInput.value = 0;
                            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        if (window.Livewire && typeof Livewire.emit === 'function') {
                            Livewire.emit('applyCustomerAdditionalDiscount', { discount: 0 });
                            Livewire.emit('applyCustomerCashDiscount', { percent: 0 });
                        }
                        */
                        $('#area').val('');
                        $('#phone').val('');
                        $('#opening_balance').val('0.00');
                        $('#excess_amount_display').val('0.00');
                        $('#excess_amount').val('0.00');
                        if (window.updateHiddenFields) window.updateHiddenFields();
                    });
                });

                var initial = $('#customer_id').val();
                if (initial) {
                    setTimeout(function () { $('#customer_id').trigger('change'); }, 100);
                }
            });
        </script>
@endpush
