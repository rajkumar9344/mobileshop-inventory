@extends('layouts.app')

@section('title', 'Customer Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <style>
        .table-striped-custom tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }
        .table-striped-custom tbody tr:nth-of-type(even) {
            background-color: #fff;
        }
        .table th, .table td {
            width: 40%;
            vertical-align: middle;
        }
        .table th {
            font-weight: 600;
        }
    </style>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-end mb-2">
                                    <a href="{{ route('customers.index') }}" class="btn btn-primary">
                                        <i class="bi bi-arrow-left"></i> Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header font-weight-bold">Customer Details</div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-striped-custom mb-0">
                                            <tr><th>Customer Name</th><td>{{ $customer->customer_name }}</td></tr>
                                            <tr><th>Customer Code</th><td>{{ $customer->customer_code }}</td></tr>
                                            <tr><th>Customer Email</th><td>{{ $customer->customer_email }}</td></tr>
                                            <tr><th>Customer Phone</th><td>{{ $customer->customer_phone }}</td></tr>
                                            <tr><th>City</th><td>{{ $customer->city }}</td></tr>
                                            <tr><th>Area</th><td>{{ $customer->area }}</td></tr>
                                            <tr><th>State</th><td>{{ $customer->state }}</td></tr>
                                            <tr><th>Pincode</th><td>{{ $customer->pincode }}</td></tr>
                                            <tr><th>Country</th><td>{{ $customer->country }}</td></tr>
                                            <tr><th>Address</th><td>{{ $customer->address }}</td></tr>
                                            <tr><th>GST No</th><td>{{ $customer->gst_no }}</td></tr>
                                            <tr><th>PAN No</th><td>{{ $customer->pan_no }}</td></tr>
                                            <tr><th>Aadhar No</th><td>{{ $customer->aadhar_no }}</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header font-weight-bold">Account Details</div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-striped-custom mb-0">
                                            <tr><th>Open Balance</th><td>{{ $customer->opening_balance }}</td></tr>
                                            <tr><th>Excess Amount</th><td>{{ number_format($customer->excess_amount ?? 0, 2, '.', '') }}</td></tr>
                                            <tr><th>Credit Limit</th><td>{{ $customer->credit_limit }}</td></tr>
                                            <tr><th>Cash Discount %</th><td>{{ $customer->cash_discount }}</td></tr>
                                            <tr><th>Additional Discount %</th><td>{{ $customer->additional_discount ?? $customer->less_discount }}</td></tr>
                                            <tr><th>Discount %</th><td>{{ $customer->discount_percent }}</td></tr>
                                            <tr><th>Outstanding Days</th><td>{{ $customer->terms_days }}</td></tr>
                                            <tr><th>Lock</th><td>{{ $customer->lock }}</td></tr>
                                            <tr><th>Outstanding</th><td>{{ $customer->outstanding }}</td></tr>
                                            <tr><th>Active</th><td>{{ $customer->is_active ? 'Yes' : 'No' }}</td></tr>
                                            <tr><th>Salesman</th><td>{{ $customer->salesman }}</td></tr>
                                            <tr><th>Account ID</th><td>{{ $customer->account_id }}</td></tr>
                                            <tr><th>Del. Mode</th><td>{{ $customer->lr_through }}</td></tr>
                                            <tr><th>Remarks</th><td>{{ $customer->remarks }}</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

