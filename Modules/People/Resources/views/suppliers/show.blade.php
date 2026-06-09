@extends('layouts.app')

@section('title', 'Supplier Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
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
                                    <a href="{{ route('suppliers.index') }}" class="btn btn-primary">
                                        <i class="bi bi-arrow-left"></i> Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header font-weight-bold">Supplier Details</div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-striped-custom mb-0">
                                            <tr><th>Supplier Name</th><td>{{ $supplier->supplier_name }}</td></tr>
                                            <tr><th>Supplier Code</th><td>{{ $supplier->supplier_code }}</td></tr>
                                            <tr><th>Email</th><td>{{ $supplier->supplier_email }}</td></tr>
                                            <tr><th>Phone</th><td>{{ $supplier->supplier_phone }}</td></tr>
                                            <tr><th>Area</th><td>{{ $supplier->area }}</td></tr>
                                            <tr><th>State</th><td>{{ $supplier->state }}</td></tr>
                                            <tr><th>City</th><td>{{ $supplier->city }}</td></tr>
                                            <tr><th>Country</th><td>{{ $supplier->country }}</td></tr>
                                            <tr><th>Address</th><td>{{ $supplier->address }}</td></tr>
                                            <tr><th>Status</th><td>{{ ucfirst($supplier->status) }}</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header font-weight-bold">Account Details</div>
                                    <div class="card-body p-0">
                                        <table class="table table-bordered table-striped-custom mb-0">
                                            <tr><th>GST No</th><td>{{ $supplier->gst_no }}</td></tr>
                                            <tr><th>Bank Name</th><td>{{ $supplier->bank_name }}</td></tr>
                                            <tr><th>Account No</th><td>{{ $supplier->account_no }}</td></tr>
                                            <tr><th>Branch</th><td>{{ $supplier->branch }}</td></tr>
                                            <tr><th>IFSC</th><td>{{ $supplier->ifsc }}</td></tr>
                                            <tr><th>Type</th><td>{{ $supplier->style }}</td></tr>
                                            <tr><th>Open Balance</th><td>{{ $supplier->open_balance }}</td></tr>
                                            <tr><th>Excess Amount</th><td>{{ number_format($supplier->excess_amount ?? 0, 2, '.', '') }}</td></tr>
                                            <tr><th>Credit Limit</th><td>{{ $supplier->credit_limit }}</td></tr>
                                            <tr><th>Tax %</th><td>{{ $supplier->tax_percent }}</td></tr>
                                            <tr><th>Discount %</th><td>{{ $supplier->less_discount_percent }}</td></tr>
                                            <tr><th>Due Days</th><td>{{ $supplier->due_days ?? 0 }}</td></tr>
                                            <tr><th>Remarks</th><td>{{ $supplier->remarks }}</td></tr>
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

