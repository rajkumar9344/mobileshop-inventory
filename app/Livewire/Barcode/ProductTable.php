<?php

namespace App\Livewire\Barcode;

use Livewire\Component;
use Milon\Barcode\Facades\DNS1DFacade;
use Modules\Product\Entities\Product;
use Barryvdh\DomPDF\Facade\Pdf;

class ProductTable extends Component
{
    public $product;
    public $quantity;
    public $barcodes;
    public $availableCodes = [];
    public $selectedCode = null;

    protected $listeners = ['productSelected'];

    public function mount() {
        $this->product = '';
        $this->quantity = 0;
        $this->barcodes = [];
    }

    public function render() {
        return view('livewire.barcode.product-table');
    }

    public function productSelected($payload) {
        // Accept either a Product model or an array/object payload dispatched by SearchProduct
        if (is_array($payload) || is_object($payload)) {
            $p = is_array($payload) ? (object) $payload : $payload;
            $this->product = \Modules\Product\Entities\Product::find($p->id ?? $p->id ?? null);

            // Collect codes from payload if available; otherwise load from relation
            $codes = [];
            if (!empty($p->product_codes) || !empty($p->product_codes)) {
                $codes = $p->product_codes;
            } elseif ($this->product) {
                $codes = $this->product->productCodes()->orderByDesc('is_primary')->pluck('code')->toArray();
            }

            if (empty($codes)) {
                $codes = [$p->product_code ?? ($this->product->product_code ?? '')];
            }

            $this->availableCodes = $codes;
            $this->selectedCode = $codes[0] ?? null;
        } else {
            // If a Product instance was passed directly
            $this->product = $payload;
            $this->availableCodes = $this->product->productCodes()->orderByDesc('is_primary')->pluck('code')->toArray() ?: [$this->product->product_code];
            $this->selectedCode = $this->availableCodes[0] ?? null;
        }

        $this->quantity = 1;
        $this->barcodes = [];
    }

    public function generateBarcodes()
    {
        // Use component state for quantity and validate
        $quantity = (int) $this->quantity;
            if ($quantity <= 0) {
                return session()->flash('message', 'Please enter a valid quantity');
            }

            if ($quantity > 100) {
                return session()->flash('message', 'Max quantity is 100 per barcode generation!');
            }

            $product = $this->product;
            if (empty($product) || !($product instanceof Product)) {
                return session()->flash('message', 'Please select a product first');
            }

            // Allow alphanumeric product codes. If the configured symbology
            // requires numeric-only data (like EAN/UPC), only fall back to C128
            // when the product code is not valid for that symbology.
            $sym = strtoupper($product->product_barcode_symbology ?? 'C128');
            $numericOnly = ['EAN13', 'EAN8', 'UPCA', 'UPCE'];

            // helper to validate the code for numeric symbologies
            $isValidForSym = function($code, $sym) use ($numericOnly) {
                if (!in_array($sym, $numericOnly)) return true;
                if (!ctype_digit(strval($code))) return false;
                $len = strlen((string)$code);
                switch ($sym) {
                    case 'EAN13': return $len === 13;
                    case 'EAN8': return $len === 8;
                    case 'UPCA': return $len === 12;
                    case 'UPCE': return $len === 6 || $len === 8; // allowance for 8 with leading zeros
                    default: return false;
                }
            };

            if (in_array($sym, $numericOnly) && ! $isValidForSym($product->product_code, $sym)) {
                $sym = 'C128';
            }

            $this->barcodes = [];

            for ($i = 1; $i <= $quantity; $i++) {
                $codeToUse = $this->selectedCode ?? ($product->product_code ?? '');
                $barcodeSvg = DNS1DFacade::getBarCodeSVG($codeToUse, $sym, 2, 60, 'black', false);
                $this->barcodes[] = [
                    'name' => $product->product_name ?? ($this->product->product_name ?? ''),
                    'product_code' => $codeToUse,
                    'barcode' => $barcodeSvg,
                    'price' => $product->mrp ?? $product->product_price ?? ($this->product->mrp ?? $this->product->product_price ?? null),
                ];
            }
    }


    // Always generate PNG for PDF to avoid UTF-8 errors

public function getPdf()
    {
    $barcodes = [];

        // Same symbology fallback for PDF generation (only when invalid)
        $sym = strtoupper($this->product->product_barcode_symbology ?? 'C128');
        $numericOnly = ['EAN13', 'EAN8', 'UPCA', 'UPCE'];

        $isValidForSym = function($code, $sym) use ($numericOnly) {
            if (!in_array($sym, $numericOnly)) return true;
            if (!ctype_digit(strval($code))) return false;
            $len = strlen((string)$code);
            switch ($sym) {
                case 'EAN13': return $len === 13;
                case 'EAN8': return $len === 8;
                case 'UPCA': return $len === 12;
                case 'UPCE': return $len === 6 || $len === 8;
                default: return false;
            }
        };

        $codeToUse = $this->selectedCode ?? ($this->product->product_code ?? '');
        if (in_array($sym, $numericOnly) && ! $isValidForSym($codeToUse, $sym)) {
            $sym = 'C128';
        }

        for ($i = 1; $i <= $this->quantity; $i++) {
            $barcode = DNS1DFacade::getBarcodePNG(
                $codeToUse,
                $sym,
                2,
                60
            );
            $barcodes[] = $barcode;
        }

    $pdf = app(\App\Services\PdfGenerator::class)->make('product::barcode.print', [
        'barcodes' => $barcodes,
        'price' => $this->product->mrp ?? $this->product->product_price ?? '',
        'name' => mb_convert_encoding($this->product->product_name ?? '', 'UTF-8', 'UTF-8'),
        'isPdf' => true,
    ], ['options' => ['encoding' => 'UTF-8']]);

    return $pdf->stream('barcodes-' . ($this->selectedCode ?? ($this->product->product_code ?? 'unknown')) . '.pdf');
}

    public function updatedQuantity() {
        $this->barcodes = [];
    }
}
