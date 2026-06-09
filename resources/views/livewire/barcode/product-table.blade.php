<div>
    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </div>
    @endif
    <div class="card">
        <div class="card-body">
            <div class="table-responsive-md">
                <table class="table table-bordered mb-0">
                    <thead>
                    <tr class="align-middle">
                        <th class="align-middle">Product Name</th>
                        <th class="align-middle">Code</th>
                        <th class="align-middle">
                            Quantity <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="Max Quantity: 100"></i>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        @if(!empty($product))
                            <td class="align-middle">{{ $product->product_name }}</td>
                            <td class="align-middle">
                                @if(!empty($availableCodes) && count($availableCodes) > 1)
                                    <select wire:model="selectedCode" class="form-control">
                                        @foreach($availableCodes as $code)
                                            <option value="{{ $code }}">{{ $code }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $product->product_code ?? ($availableCodes[0] ?? '') }}
                                @endif
                            </td>
                            <td class="align-middle text-center" style="width: 200px;">
                                <input wire:model.live="quantity" class="form-control" type="number" min="1" max="100" value="{{ $quantity }}">
                            </td>
                        @else
                            <td colspan="3" class="text-center">
                                <span class="text-danger">Please search & select a product!</span>
                            </td>
                        @endif
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 d-flex justify-content-end">
                <button wire:click="generateBarcodes" type="button" class="btn btn-primary">
                    <i class="bi bi-upc-scan"></i> Generate Barcodes
                </button>
            </div>
        </div>
    </div>

    <div wire:loading wire:target="generateBarcodes" class="w-100">
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>

    @if(!empty($barcodes))
        <div class="text-right mb-3">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fa fa-print"></i> Print barcode
            </button>
        </div>
        <div class="card">
            <div class="card-body">
                <div id="barcode-print-area" class="row justify-content-center">
                    @foreach($barcodes as $barcode)
                        <div class="col-lg-3 col-md-4 col-sm-6" style="border: 1px solid #ffffff;border-style: dashed;background-color: #48FCFE; padding:12px 10px 22px;">
                            <p class="mt-3 mb-1" style="font-size: 15px;color: #000;">
                                {{ $barcode['name'] ?? ($product->product_name ?? '') }} - {{ $barcode['product_code'] ?? ($product->product_code ?? ($availableCodes[0] ?? '')) }}
                            </p>
                            <div style="margin-bottom:12px;">
                                {!! $barcode['barcode'] ?? $barcode !!}
                            </div>
                            <p style="font-size: 15px;color: #000; margin-bottom:0;">
                                MRP:: {{ isset($barcode['price']) ? format_currency($barcode['price']) : (isset($product->mrp) ? format_currency($product->mrp) : (isset($product->product_price) ? format_currency($product->product_price) : '')) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <style>
        /* Ensure barcode SVGs fit inside their card and do not overflow the turquoise background.
           Make SVGs block-level and scale responsively. Also hide overflow from the card. */
        #barcode-print-area .card-body > .row > div { overflow: hidden; }
        #barcode-print-area svg, #barcode-print-area img {
            display: block;
            max-width: 100%;
            height: auto;
        }

        @media print {
            body * { visibility: hidden !important; }
            #barcode-print-area, #barcode-print-area * { visibility: visible !important; }
            #barcode-print-area { position: absolute; left: 0; top: 0; width: 100vw; background: #fff; }
        }
        </style>
    @endif
</div>
