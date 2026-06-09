<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Milon\Barcode\Facades\DNS1DFacade;

class BarcodeController extends Controller
{
    public function printBarcode(Request $request)
    {
        abort_if(Gate::denies('print_product_barcodes'), 403);

        // Example static product list – replace with your actual product fetch logic
        $products = [
            ['name' => 'Necklace', 'price' => 100, 'code' => '101010'],
            ['name' => 'Blender', 'price' => 700, 'code' => '101034'],
        ];

        $barcodes = [];

        foreach ($products as $product) {
            // getBarcodePNG returns a base64-encoded PNG string (not double-encoded)
            $png = DNS1DFacade::getBarcodeSVG($product['code'], 'C128', 2, 60);
            $barcodes[] = [
                'name' => $product['name'],
                'price' => $product['price'],
                // Prefix with data URI for direct <img> usage
                'barcode' => 'data:image/png;base64,' . $png,
            ];
        }

        return view('product::barcode.index', compact('barcodes'));
    }

    public function downloadBarcodePdf(Request $request)
    {
        abort_if(Gate::denies('print_product_barcodes'), 403);

        // Example static product list – replace with your actual product fetch logic
        $products = [
            ['name' => 'Necklace', 'price' => 1500, 'code' => 'NECK123'],
            ['name' => 'Ring', 'price' => 700, 'code' => 'RING456'],
        ];

        $barcodes = [];

        foreach ($products as $product) {
            // getBarcodePNG returns a base64-encoded PNG string (not double-encoded)
            $png = DNS1DFacade::getBarcodePNG($product['code'], 'C128', 2, 60);
            $barcodes[] = [
                'name' => $product['name'],
                'price' => $product['price'],
                // Pass only the base64 string, add prefix in Blade
                'barcode' => $png,
            ];
        }

        $pdf = app('dompdf.wrapper')->loadView('product::barcode.pdf', compact('barcodes'));
        return $pdf->stream('barcodes.pdf');
    }
}