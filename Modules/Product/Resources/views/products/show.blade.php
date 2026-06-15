@extends('layouts.app')

@section('title', 'Product Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="font-weight-bold mb-0">Product Details</h5>
                            <a href="{{ route('products.index') }}" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                    <tr>
                                        <th>Brand</th>
                                        <td>{{ $product->category->category_name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Product Name</th>
                                        <td>{{ $product->product_name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Code</th>
                                        <td>{{ $product->product_code }}</td>
                                    </tr>
                                    <tr>
                                        <th>Equivalent Product's Code</th>
                                        <td>{{ $product->alternative_number ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Unit</th>
                                        <td>{{ $product->product_unit ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Open Quantity</th>
                                        <td>{{ $product->open_quantity ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Purchase Quantity</th>
                                        <td>{{ $product->purchase_quantity ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Current quantity(Overall)</th>
                                        <td>{{ $product->product_quantity ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Alert Quantity</th>
                                        <td>{{ $product->product_stock_alert ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>VAT (%)</th>
                                        <td>{{ $product->product_order_tax ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>VAT Type</th>
                                        <td>
                                            @if($product->product_tax_type == 1)
                                                Exclusive
                                            @elseif($product->product_tax_type == 2)
                                                Inclusive
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Buy Price</th>
                                        <td>{{ format_currency($product->buy_price) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Purchase Rate</th>
                                        <td>{{ format_currency($product->product_cost) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Sell Rate</th>
                                        <td>{{ format_currency($product->product_price) }}</td>
                                    </tr>
                                    <tr>
                                        <th>List Price</th>
                                        <td>{{ format_currency($product->list_price) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>{{ $product->status ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Comments</th>
                                        <td>{{ $product->product_note ?? 'N/A' }}</td>
                                    </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection



