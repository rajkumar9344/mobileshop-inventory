@extends('layouts.app')

@section('title', 'Edit Customer')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('customers.update', $customer) }}" method="POST">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5>Customer Details</h5>
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="customer_name">Customer Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="customer_name" required maxlength="80" value="{{ $customer->customer_name }}">
                                        @error('customer_name')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="customer_phone">Phone <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="customer_phone" id="customer_phone" required maxlength="10"
                                            pattern="[0-9]{10}" title="Please enter exactly 10 digits" value="{{ $customer->customer_phone }}"
                                            oninput="validatePhone(this); document.getElementById('customer_code').value = this.value;">
                                        <small id="phone-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="customer_email">Email</label>
                                        <input type="email" class="form-control" name="customer_email" id="customer_email" maxlength="50"
                                            value="{{ $customer->customer_email }}"
                                            oninput="this.value = this.value.replace(/[^a-zA-Z0-9@._]/g, '').slice(0,50); validateEmail(this);">
                                        <small id="email-error" class="text-danger" style="display: none;"></small>
                                        @error('customer_email')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="customer_code">Customer Code (Auto-filled from Mobile) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="customer_code" id="customer_code" required maxlength="10"
                                            pattern="[A-Za-z0-9]+" value="{{ $customer->customer_code }}"
                                            oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g,'').slice(0,10);">
                                        @error('customer_code')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="city">City</label>
                                        <input type="text" class="form-control" name="city" maxlength="30" value="{{ $customer->city }}">
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="vat_id">VAT ID / TRN <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="vat_id" maxlength="20" required
                                            value="{{ $customer->vat_id }}"
                                            oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,20)">
                                        @error('vat_id')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="state">State</label>
                                        <input type="text" class="form-control" name="state" maxlength="30" value="{{ $customer->state }}">
                                        @error('state')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="pincode">Pincode</label>
                                        <input type="text" class="form-control" name="pincode" maxlength="10"
                                            value="{{ $customer->pincode }}"
                                            oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,10)">
                                        @error('pincode')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="area">Area <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="area" maxlength="30" required value="{{ $customer->area }}">
                                        @error('area')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <textarea class="form-control" name="address" rows="3" maxlength="200">{{ $customer->address }}</textarea>
                                        @error('address')<small class="text-danger">{{ $message }}</small>@enderror
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
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="opening_balance">Open Balance <span class="text-danger">*</span></label>
                                        <x-currency-input id="opening_balance" displayName="opening_balance_display" hiddenName="opening_balance" :value="old('opening_balance', $customer->opening_balance)" />
                                        @error('opening_balance')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="excess_amount">Excess Amount</label>
                                        <x-currency-input id="excess_amount" displayName="excess_amount_display" hiddenName="excess_amount" :value="old('excess_amount', $customer->excess_amount ?? 0)" />
                                        @error('excess_amount')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="credit_limit">Credit Limit <span class="text-danger">*</span></label>
                                        <x-currency-input id="credit_limit" displayName="credit_limit_display" hiddenName="credit_limit" :value="old('credit_limit', $customer->credit_limit)" />
                                        @error('credit_limit')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="account_id">Account ID</label>
                                        <input type="text" class="form-control" name="account_id" maxlength="10"
                                            value="{{ $customer->account_id }}"
                                            oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g,'').slice(0,10)">
                                        @error('account_id')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="is_active">Active <span class="text-danger">*</span></label>
                                        <select name="is_active" class="form-control" required>
                                            <option value="1" {{ $customer->is_active ? 'selected' : '' }}>Yes</option>
                                            <option value="0" {{ !$customer->is_active ? 'selected' : '' }}>No</option>
                                        </select>
                                        @error('is_active')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="lock">Lock <span class="text-danger">*</span></label>
                                        <select name="lock" class="form-control" required>
                                            <option value="No" {{ ($customer->lock ?? 'No') === 'No' ? 'selected' : '' }}>No</option>
                                            <option value="Yes" {{ ($customer->lock ?? 'No') === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        </select>
                                        @error('lock')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="outstanding">Outstanding <span class="text-danger">*</span></label>
                                        <select name="outstanding" class="form-control" required>
                                            <option value="No" {{ ($customer->outstanding ?? 'No') === 'No' ? 'selected' : '' }}>No</option>
                                            <option value="Yes" {{ ($customer->outstanding ?? 'No') === 'Yes' ? 'selected' : '' }}>Yes</option>
                                        </select>
                                        @error('outstanding')<small class="text-danger">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label for="remarks">Remarks</label>
                                        <textarea class="form-control" name="remarks" rows="2" maxlength="200">{{ $customer->remarks }}</textarea>
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
                    <a href="{{ route('customers.index') }}" class="btn btn-secondary mr-2">Back</a>
                    <button type="submit" class="btn btn-primary ml-2">Update Customer <i class="bi bi-check"></i></button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/currency-input.js') }}"></script>
    <script src="{{ asset('js/validation.js') }}"></script>
@endpush
