@extends('layouts.app')

@section('title', 'Edit Sale Return')

@section('breadcrumb')
    @php $isReadOnly = !empty($readonly); @endphp
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-returns.index') }}">Sale Returns</a></li>
        <li class="breadcrumb-item active">{{ $isReadOnly ? 'View' : 'Edit' }}</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')
                        @php $isReadOnly = !empty($readonly); @endphp
                        <form id="sale-return-form" action="{{ $isReadOnly ? '#' : route('sale-returns.update', $sale_return) }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required value="{{ $sale_return->reference }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                            <select class="form-control" name="customer_id" id="customer_id" {{ $isReadOnly ? 'disabled' : 'required' }}>
                                                    @foreach(\Modules\People\Entities\Customer::where(function($q) use ($selectedCustomer){ $q->where('is_active', true)->orWhere('id', $selectedCustomer); })->orderBy('customer_name','asc')->get() as $customer)
                                                        <option value="{{ $customer->id }}" {{ ((string)$selectedCustomer === (string)$customer->id) ? 'selected' : '' }}>{{ $customer->customer_name }}{{ !$customer->is_active ? ' (Inactive)' : '' }}</option>
                                                    @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="date">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" {{ $isReadOnly ? 'disabled' : 'required' }} value="{{ $sale_return->date }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-row mb-3">
                                <div class="col-md-3 pr-1">
                                    <label for="area" class="mb-1">Area</label>
                                    <input type="text" class="form-control" name="area" id="area" maxlength="30" placeholder="Area" value="{{ old('area', $sale_return->area ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="phone" class="mb-1">Phone No</label>
                                    <input type="tel" class="form-control" name="phone" id="phone" maxlength="10" pattern="[0-9]{10}" title="Please enter exactly 10 digits" oninput="validatePhone(this)" placeholder="9876543210" value="{{ old('phone', $sale_return->phone_no ?? '') }}" {{ $isReadOnly ? 'disabled' : '' }}>
                                    <small id="phone-error" class="text-danger" style="display: none;"></small>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="opening_balance" class="mb-1">Balance</label>
                                    <input type="text" class="form-control" name="opening_balance" id="opening_balance" maxlength="15" pattern="^-?\d+(?:\.\d{1,2})?$|^-?\d+(?:,\d{1,2})?$" value="{{ number_format($sale_return->balance ?? 0, 2, '.', '') }}" placeholder="0.00" readonly>
                                </div>
                                <div class="col-md-3 pr-1">
                                    <label for="excess_amount_display" class="mb-1">Excess Amount</label>
                                    <input type="text" class="form-control" id="excess_amount_display" readonly value="{{ old('excess_amount', $sale_return->excess_amount ?? '0.00') }}">
                                    <input type="hidden" name="excess_amount" id="excess_amount" value="{{ old('excess_amount', $sale_return->excess_amount ?? '0.00') }}">
                                </div>
                            </div>

                            {{-- Product search placed directly above the product cart for easier access --}}
                            @unless($isReadOnly)
                                <div class="mb-3">
                                    <livewire:search-product/>
                                </div>
                            @endunless

                            <livewire:product-cart :cartInstance="$cartInstance ?? 'sale_return'" :data="$sale_return" :readonly="$isReadOnly"/>

                            <!-- Hidden required calculation fields (mirrors create view) -->
                            <input type="hidden" name="tax_percentage" id="hidden_tax_percentage" value="{{ old('tax_percentage', $sale_return->tax_percentage ?? 0) }}">
                            <input type="hidden" name="discount_percentage" id="hidden_discount_percentage" value="{{ old('discount_percentage', $sale_return->discount_percentage ?? 0) }}">
                            <input type="hidden" name="shipping_amount" id="hidden_shipping_amount" value="{{ old('shipping_amount', $sale_return->shipping_amount ?? 0) }}">
                            <input type="hidden" name="total_amount" id="hidden_total_amount" value="{{ old('total_amount', $sale_return->total_amount ?? 0) }}">
                            <input type="hidden" name="status" value="{{ old('status', $sale_return->status ?? 'Pending') }}">

                            <!-- Hidden fields for Overall Calculations (populated from ProductCart display) -->
                            <input type="hidden" name="overall_nos" id="hidden_overall_nos" value="{{ old('overall_nos', $sale_return->overall_nos ?? 0) }}">
                            <input type="hidden" name="overall_quantity" id="hidden_overall_quantity" value="{{ old('overall_quantity', $sale_return->overall_quantity ?? 0) }}">
                            <input type="hidden" name="overall_gross_amount" id="hidden_overall_gross_amount" value="{{ old('overall_gross_amount', $sale_return->overall_gross_amount ?? 0) }}">
                            <input type="hidden" name="overall_taxable_amount" id="hidden_overall_taxable_amount" value="{{ old('overall_taxable_amount', $sale_return->overall_taxable_amount ?? 0) }}">
                            <input type="hidden" name="overall_cgst" id="hidden_overall_cgst" value="{{ old('overall_cgst', $sale_return->overall_cgst ?? 0) }}">
                            <input type="hidden" name="overall_sgst" id="hidden_overall_sgst" value="{{ old('overall_sgst', $sale_return->overall_sgst ?? 0) }}">
                            <input type="hidden" name="overall_igst" id="hidden_overall_igst" value="{{ old('overall_igst', $sale_return->overall_igst ?? 0) }}">
                            <input type="hidden" name="overall_tax_amount" id="hidden_overall_tax_amount" value="{{ old('overall_tax_amount', $sale_return->overall_tax_amount ?? 0) }}">
                            <input type="hidden" name="overall_tcs_percent" id="hidden_overall_tcs_percent" value="{{ old('overall_tcs_percent', $sale_return->overall_tcs_percent ?? 0) }}">
                            <input type="hidden" name="overall_amount" id="hidden_overall_amount" value="{{ old('overall_amount', $sale_return->overall_amount ?? 0) }}">
                            <input type="hidden" name="overall_other" id="hidden_overall_other" value="{{ old('overall_other', $sale_return->overall_other ?? 0) }}">
                            <input type="hidden" name="overall_adj" id="hidden_overall_adj" value="{{ old('overall_adj', $sale_return->overall_adj ?? 0) }}">
                            <input type="hidden" name="overall_net_rate" id="hidden_overall_net_rate" value="{{ old('overall_net_rate', $sale_return->overall_net_rate ?? 0) }}">

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>{{ $sale_return->note }}</textarea>
                            </div>

                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="create_receipt" name="create_receipt" value="1" {{ old('create_receipt', $sale_return->create_receipt) ? 'checked' : '' }} {{ $isReadOnly ? 'disabled' : '' }}>
                                <label class="form-check-label" for="create_receipt">Create receipt for this sale return</label>
                                <small class="form-text text-muted">If checked, a linked receipt will be created using the overall net amount.</small>
                            </div>

                            <div class="mt-3 d-flex justify-content-end">
                                <a href="{{ route('sale-returns.index') }}" class="btn btn-secondary mr-2">Back</a>
                                @unless($isReadOnly)
                                    <button type="submit" class="btn btn-primary">
                                        Update Sale Return <i class="bi bi-check"></i>
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
    <script src="{{ asset('js/jquery-mask-money.js') }}"></script>
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script>
        // Define updateHiddenFields (same logic used in create view)
        function updateHiddenFields() {
            // Default percentages/amounts
            const taxEl = document.getElementById('hidden_tax_percentage');
            if (taxEl) taxEl.value = '0';
            const discEl = document.getElementById('hidden_discount_percentage');
            if (discEl) discEl.value = '0';
            const shipEl = document.getElementById('hidden_shipping_amount');
            if (shipEl) shipEl.value = '0';

            // Prefer the Overall Net Rate field from the ProductCart Livewire component if present
            const overallNetEl = document.getElementById('overall_net_rate');
            let cartTotal = '0';
            if (overallNetEl) {
                cartTotal = overallNetEl.value || overallNetEl.textContent || '0';
            } else {
                const cartTotalElement = document.querySelector('.table-responsive .table .table-striped tr:last-child th:last-child');
                cartTotal = cartTotalElement ? cartTotalElement.textContent : '0';
            }
            cartTotal = String(cartTotal).replace(/[^0-9\.\-]/g, '') || '0';
            const hiddenTotal = document.getElementById('hidden_total_amount');
            if (hiddenTotal) hiddenTotal.value = cartTotal;

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

        // Expose globally
        window.updateHiddenFields = updateHiddenFields;

        // Wire up update calls
        $(document).ready(function () {
            // Populate hidden calculation fields on load and before submit
            if (typeof updateHiddenFields === 'function') updateHiddenFields();

            // Ensure hidden fields are up-to-date before submit
            $('#sale-return-form').submit(function () {
                if (typeof updateHiddenFields === 'function') updateHiddenFields();
            });

            // Watch product cart area for DOM changes and refresh hidden fields
            const cartContainer = document.querySelector('.table-responsive');
            if (cartContainer && window.MutationObserver) {
                const observer = new MutationObserver(function () {
                    if (typeof updateHiddenFields === 'function') updateHiddenFields();
                });
                observer.observe(cartContainer, { childList: true, subtree: true, characterData: true });
            }

            // When a customer is selected, populate area, phone, balance
            $('#customer_id').on('change', function () {
                var id = $(this).val();
                if (!id) return;
                var url = '/customers/' + id + '/json';
                $.get(url).done(function (res) {
                    $('#area').val(res.area || '');
                    $('#phone').val(res.customer_phone || '');
                    $('#opening_balance').val(res.opening_balance !== undefined ? res.opening_balance : '0.00');
                    // Populate excess amount
                    if (res.excess_amount !== undefined) {
                        $('#excess_amount_display').val(res.excess_amount);
                        $('#excess_amount').val(res.excess_amount);
                    } else {
                        $('#excess_amount_display').val('0.00');
                        $('#excess_amount').val('0.00');
                    }
                }).fail(function () {
                    $('#area').val('');
                    $('#phone').val('');
                    $('#opening_balance').val('0.00');
                    $('#excess_amount_display').val('0.00');
                    $('#excess_amount').val('0.00');
                });
            });
        });
    </script>
@endpush
