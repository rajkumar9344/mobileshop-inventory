@extends('layouts.app')

@section('title', 'Update Supplier')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@push('scripts')
<script src="{{ asset('js/validation.js') }}"></script>
<script src="{{ asset('js/currency-input.js') }}"></script>
<script>
function sanitizeSupplierForm(e) {
    const f = e.target;
    if (f.account_no) f.account_no.value = f.account_no.value.replace(/[^0-9]/g, '').slice(0,20);
    if (f.open_balance) f.open_balance.value = f.open_balance.value.replace(/,/g, '').trim();
    if (f.excess_amount) f.excess_amount.value = f.excess_amount.value.replace(/,/g, '').trim();
    if (f.credit_limit) f.credit_limit.value = f.credit_limit.value.replace(/,/g, '').trim();
    if (f.less_discount_percent) f.less_discount_percent.value = f.less_discount_percent.value.replace(/%/g, '').trim();
    if (f.tax_percent) f.tax_percent.value = f.tax_percent.value.replace(/[^0-9.]/g, '').trim();
    if (f.due_days) f.due_days.value = f.due_days.value.replace(/[^0-9]/g, '').trim();
    if (f.supplier_email) f.supplier_email.value = f.supplier_email.value.replace(/[^A-Za-z0-9@._]/g,'');
    return true;
}

function attachInputFilters() {
    const filters = {
        supplier_code: /[^A-Za-z0-9]/g,
        // supplier_phone: /[^0-9\s\+]/g, // Removed to use shared validation
        gst_no: /[^A-Za-z0-9]/g,
        ifsc: /[^A-Za-z0-9]/g,
        account_no: /[^0-9]/g,
        open_balance: /[^0-9,\.]/g,
        excess_amount: /[^0-9,\.]/g,
        credit_limit: /[^0-9,\.]/g,
        tax_percent: /[^0-9.]/g,
        less_discount_percent: /[^0-9.%]/g,
        due_days: /[^0-9]/g,
        // 'style' removed, type is a select so no input filter required
        supplier_email: /[^A-Za-z0-9@._]/g
    };

    Object.keys(filters).forEach(function(name){
        const el = document.querySelector('[name="'+name+'"]');
        if (!el) return;
        el.addEventListener('input', function(){
            const start = this.selectionStart;
            const old = this.value;
            const cleaned = old.replace(filters[name], '');
            if (cleaned !== old) {
                this.value = cleaned;
                try { this.setSelectionRange(Math.max(0, start-1), Math.max(0, start-1)); } catch(e){}
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', attachInputFilters);
</script>
@endpush

@section('content')
    <div class="container-fluid">
    <form action="{{ route('suppliers.update', $supplier) }}" method="POST" onsubmit="sanitizeSupplierForm(event)">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5>Supplier Details</h5>
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="supplier_name" required maxlength="80" value="{{ old('supplier_name', $supplier->supplier_name) }}">
                                        @error('supplier_name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="supplier_code">Supplier Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="supplier_code" required maxlength="10" pattern="[a-zA-Z0-9]+" value="{{ old('supplier_code', $supplier->supplier_code) }}">
                                        @error('supplier_code')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="supplier_email">Email</label>
                                        <input type="email" class="form-control" name="supplier_email" id="supplier_email" maxlength="50" value="{{ old('supplier_email', $supplier->supplier_email) }}" oninput="this.value = this.value.replace(/[^a-zA-Z0-9@._]/g, '').slice(0,50); validateEmail(this); this.value = this.value.toLowerCase()">
                                        <small id="email-error" class="text-danger" style="display: none;"></small>
                                        @error('supplier_email')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="supplier_phone">Phone <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="supplier_phone" id="supplier_phone" required maxlength="10" pattern="[0-9]{10}" title="Please enter exactly 10 digits" value="{{ old('supplier_phone', $supplier->supplier_phone) }}" oninput="validatePhone(this)">
                                        <small id="phone-error" class="text-danger" style="display: none;"></small>
                                        @error('supplier_phone')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="area">Area <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="area" required maxlength="30" value="{{ old('area', $supplier->area) }}">
                                        @error('area')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="state">State <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="state" required maxlength="30" value="{{ old('state', $supplier->state) }}">
                                        @error('state')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <input type="text" class="form-control" name="address" maxlength="200" value="{{ old('address', $supplier->address) }}">
                                        @error('address')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="city">City</label>
                                        <input type="text" class="form-control" name="city" maxlength="30" value="{{ old('city', $supplier->city) }}">
                                        @error('city')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="country">Country</label>
                                        <input type="text" class="form-control" name="country" maxlength="30" value="{{ old('country', $supplier->country) }}">
                                        @error('country')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5>Account Details</h5>
                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="type">Type</label>
                                        <select class="form-control" name="type">
                                            <option value="1" {{ old('type', $supplier->style ?? 1) == 1 ? 'selected' : '' }}>Type 1</option>
                                            <option value="2" {{ old('type', $supplier->style ?? 1) == 2 ? 'selected' : '' }}>Type 2</option>
                                            <option value="3" {{ old('type', $supplier->style ?? 1) == 3 ? 'selected' : '' }}>Type 3</option>
                                            <option value="4" {{ old('type', $supplier->style ?? 1) == 4 ? 'selected' : '' }}>Type 4</option>
                                        </select>
                                        @error('style')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="due_days">Due Days <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="due_days" maxlength="3" pattern="[0-9]*" inputmode="numeric" value="{{ old('due_days', $supplier->due_days ?? 0) }}" required oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,3)">
                                        @error('due_days')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="less_discount_percent">Discount %</label>
                                        <input type="text" class="form-control" name="less_discount_percent" maxlength="5" pattern="[0-9.%]*" inputmode="decimal" oninput="this.value = this.value.replace(/[^0-9.%]/g,'').slice(0,5)" value="{{ old('less_discount_percent', $supplier->less_discount_percent !== null ? (float)$supplier->less_discount_percent : '') }}">
                                        @error('less_discount_percent')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="open_balance_display">Open Balance</label>
                                        <x-currency-input id="open_balance_display" hiddenName="open_balance" class="form-control" hiddenId="open_balance_raw" display="{{ old('open_balance', $supplier->open_balance) }}" maxlength="20" aria-label="Open balance" />
                                        @error('open_balance')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="excess_amount_display">Excess Amount</label>
                                        <x-currency-input id="excess_amount_display" hiddenName="excess_amount" class="form-control" hiddenId="excess_amount_raw" display="{{ old('excess_amount', $supplier->excess_amount ?? '') }}" maxlength="20" aria-label="Excess amount" />
                                        @error('excess_amount')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="credit_limit_display">Credit Limit</label>
                                        <x-currency-input id="credit_limit_display" hiddenName="credit_limit" class="form-control" hiddenId="credit_limit_raw" display="{{ old('credit_limit', $supplier->credit_limit ?? '') }}" maxlength="20" aria-label="Credit limit" />
                                        @error('credit_limit')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="tax_percent">Tax %</label>
                                        <input type="number" class="form-control" name="tax_percent" min="0" max="100" step="0.01" oninput="if(this.value.length > 5) this.value = this.value.slice(0, 5);" value="{{ old('tax_percent', $supplier->tax_percent) }}">
                                        @error('tax_percent')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="gst_no">GST No</label>
                                        <input type="text" class="form-control" name="gst_no" maxlength="15" pattern="[a-zA-Z0-9]+" value="{{ old('gst_no', $supplier->gst_no) }}">
                                        @error('gst_no')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="bank_name">Bank Name</label>
                                        <input type="text" class="form-control" name="bank_name" maxlength="50" value="{{ old('bank_name', $supplier->bank_name) }}">
                                        @error('bank_name')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="account_no">Account No</label>
                                        <input type="text" class="form-control" name="account_no" maxlength="20" pattern="[0-9]*" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,20)" value="{{ old('account_no', $supplier->account_no) }}">
                                        @error('account_no')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="ifsc">IFSC</label>
                                        <input type="text" class="form-control" name="ifsc" maxlength="20" pattern="[a-zA-Z0-9]+" value="{{ old('ifsc', $supplier->ifsc) }}">
                                        @error('ifsc')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="branch">Branch</label>
                                        <input type="text" class="form-control" name="branch" maxlength="50" value="{{ old('branch', $supplier->branch) }}">
                                        @error('branch')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control" name="status" required>
                                            <option value="active" {{ old('status', $supplier->status) == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ old('status', $supplier->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                        @error('status')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label for="remarks">Remarks</label>
                                        <textarea class="form-control" name="remarks" maxlength="200">{{ old('remarks', $supplier->remarks) }}</textarea>
                                        @error('remarks')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3 mb-3">
                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('suppliers.index') }}" class="btn btn-secondary mr-2">Back</a>
                    <button type="submit" class="btn btn-primary ml-2">Update Supplier <i class="bi bi-check"></i></button>
                </div>
            </div>
        </form>
    </div>
@endsection

