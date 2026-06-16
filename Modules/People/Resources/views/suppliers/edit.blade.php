@extends('layouts.app')

@section('title', 'Update Supplier')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('suppliers.update', $supplier) }}" method="POST">
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
                                        <label>Supplier Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="supplier_name" required maxlength="80"
                                            value="{{ old('supplier_name', $supplier->supplier_name) }}">
                                        @error('supplier_name')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Supplier Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="supplier_code" required maxlength="10"
                                            value="{{ old('supplier_code', $supplier->supplier_code) }}"
                                            oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g,'').slice(0,10)">
                                        @error('supplier_code')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" class="form-control" name="supplier_email" id="supplier_email" maxlength="50"
                                            value="{{ old('supplier_email', $supplier->supplier_email) }}"
                                            oninput="this.value = this.value.replace(/[^a-zA-Z0-9@._]/g,'').slice(0,50); validateEmail(this); this.value = this.value.toLowerCase()">
                                        <small id="email-error" class="text-danger" style="display:none;"></small>
                                        @error('supplier_email')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Phone <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="supplier_phone" id="supplier_phone" required
                                            maxlength="15" pattern="\+?[0-9]{7,15}" title="Only numbers and + (UAE contact number, max 15)"
                                            value="{{ old('supplier_phone', $supplier->supplier_phone) }}"
                                            oninput="validatePhone(this)">
                                        <small id="phone-error" class="text-danger" style="display:none;"></small>
                                        @error('supplier_phone')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Area <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="area" required maxlength="30"
                                            value="{{ old('area', $supplier->area) }}">
                                        @error('area')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>State</label>
                                        <input type="text" class="form-control" name="state" maxlength="30"
                                            value="{{ old('state', $supplier->state) }}">
                                        @error('state')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-9">
                                    <div class="form-group">
                                        <label>Address</label>
                                        <input type="text" class="form-control" name="address" maxlength="200"
                                            value="{{ old('address', $supplier->address) }}">
                                        @error('address')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>City</label>
                                        <input type="text" class="form-control" name="city" maxlength="30"
                                            value="{{ old('city', $supplier->city) }}">
                                        @error('city')<small class="text-danger">{{ $message }}</small>@enderror
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
                                        <label>Open Balance</label>
                                        <x-currency-input id="open_balance_display" hiddenName="open_balance" class="form-control"
                                            hiddenId="open_balance_raw" display="{{ old('open_balance', $supplier->open_balance) }}" maxlength="20" />
                                        @error('open_balance')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Excess Amount</label>
                                        <x-currency-input id="excess_amount_display" hiddenName="excess_amount" class="form-control"
                                            hiddenId="excess_amount_raw" display="{{ old('excess_amount', $supplier->excess_amount ?? '') }}" maxlength="20" readonly />
                                        @error('excess_amount')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Credit Limit</label>
                                        <x-currency-input id="credit_limit_display" hiddenName="credit_limit" class="form-control"
                                            hiddenId="credit_limit_raw" display="{{ old('credit_limit', $supplier->credit_limit ?? '') }}" maxlength="20" />
                                        @error('credit_limit')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>VAT %</label>
                                        <input type="number" class="form-control" name="tax_percent" min="0" max="100" step="0.01"
                                            value="{{ old('tax_percent', $supplier->tax_percent) }}"
                                            oninput="if(this.value.length > 5) this.value = this.value.slice(0, 5);">
                                        @error('tax_percent')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Status <span class="text-danger">*</span></label>
                                        <select class="form-control" name="status" required>
                                            <option value="active" {{ old('status', $supplier->status) == 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ old('status', $supplier->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                        @error('status')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label>Remarks</label>
                                        <textarea class="form-control" name="remarks" maxlength="200">{{ old('remarks', $supplier->remarks) }}</textarea>
                                        @error('remarks')<small class="text-danger">{{ $message }}</small>@enderror
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

@push('scripts')
    <script src="{{ asset('js/validation.js') }}"></script>
    <script src="{{ asset('js/currency-input.js') }}"></script>
@endpush
