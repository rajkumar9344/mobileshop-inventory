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
        <div class="row mb-3">
            <div class="col-md-12">
                @php
                    $sym = strtoupper($product->product_barcode_symbology ?? 'C128');
                    $numericOnly = ['EAN13','EAN8','UPCA','UPCE'];
                    $code = (string) $product->product_code;
                    $isValidForSym = function($code, $sym) use ($numericOnly) {
                        if (!in_array($sym, $numericOnly)) return true;
                        if (!ctype_digit($code)) return false;
                        $len = strlen($code);
                        switch ($sym) {
                            case 'EAN13': return $len === 13;
                            case 'EAN8': return $len === 8;
                            case 'UPCA': return $len === 12;
                            case 'UPCE': return $len === 6 || $len === 8;
                            default: return false;
                        }
                    };
                    if (in_array($sym, $numericOnly) && ! $isValidForSym($code, $sym)) {
                        // fallback to Code128 for display if code is not numeric/valid
                        $sym = 'C128';
                    }
                @endphp

                {!! \Milon\Barcode\Facades\DNS1DFacade::getBarCodeSVG($product->product_code, $sym, 2, 110) !!}
            </div>
        </div>
        <div class="row">
            <div class="col-lg-9">
                <div class="card h-100">
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
                                        <th>Subcategory</th>
                                        <td>{{ $product->subcategory->subcategory_name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Product Name</th>
                                        <td>{{ $product->product_name }}</td>
                                    </tr>
                                    <tr>
                                        <th>Code</th>
                                        <td>
                                            {{ $product->product_code }}
                                            @php
                                                $additionalCodes = isset($product->productCodes) ? collect($product->productCodes)->where('is_primary', false)->pluck('code')->all() : [];
                                            @endphp
                                            @if(!empty($additionalCodes))
                                                @foreach($additionalCodes as $c)
                                                    <span class="badge badge-secondary ml-2">{{ $c }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Equivalent Product's Code</th>
                                        <td>{{ $product->alternative_number ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Barcode Symbology</th>
                                        <td>{{ $product->product_barcode_symbology }}</td>
                                    </tr>
                                    <tr>
                                        <th>Unit</th>
                                        <td>{{ $product->product_unit ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Rack No</th>
                                        <td>{{ $product->rack_no ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Bin No</th>
                                        <td>{{ $product->bin_no ?? 'N/A' }}</td>
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
                                        <th>Tax (%)</th>
                                        <td>{{ $product->product_order_tax ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tax Type</th>
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
                                        <td>{{ $product->buy_price ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Purchase Rate (Net Rate)</th>
                                        <td>{{ format_currency($product->product_cost) }}</td>
                                    </tr>
                                    <tr>
                                        <th>MRP</th>
                                        <td>{{ $product->mrp ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Sell Rate</th>
                                        <td>{{ format_currency($product->product_price) }}</td>
                                    </tr>
                                    <tr>
                                        <th>List Price</th>
                                        <td>{{ $product->list_price ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>HSN</th>
                                        <td>{{ $product->hsn ?? 'N/A' }}</td>
                                    </tr>
                                    {{-- Location removed per UI change request --}}
                                    <tr>
                                        <th>Status</th>
                                        <td>{{ $product->status ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Compatibility</th>
                                        <td>{{ $product->product_note ?? 'N/A' }}</td>
                                    </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        @forelse($product->getMedia('images') as $media)
                            <img src="{{ $media->getUrl() }}" alt="Product Image" class="img-fluid img-thumbnail mb-2">
                        @empty
                            <img src="{{ $product->getFirstMediaUrl('images') }}" alt="Product Image" class="img-fluid img-thumbnail mb-2">
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection



