<?php

namespace App\Livewire\Barcode;

use Livewire\Component;
use Milon\Barcode\Facades\DNS1DFacade;
use Modules\Bin\Entities\Bin;
use Barryvdh\DomPDF\Facade\Pdf;

class BinTable extends Component
{
    public $bin;
    public $quantity;
    public $barcodes;

    protected $listeners = ['binSelected'];

    public function mount() {
        $this->bin = '';
        $this->quantity = 0;
        $this->barcodes = [];
    }

    public function render() {
        return view('livewire.barcode.bin-table');
    }

    public function binSelected(Bin $bin) {
        $this->bin = $bin;
        $this->quantity = 1;
        $this->barcodes = [];
    }

    public function generateBarcodes()
    {
        $quantity = (int) $this->quantity;

        if ($quantity <= 0) {
            return session()->flash('message', 'Please enter a valid quantity');
        }

        if ($quantity > 100) {
            return session()->flash('message', 'Max quantity is 100 per barcode generation!');
        }

        $bin = $this->bin;
        if (empty($bin) || !($bin instanceof Bin)) {
            return session()->flash('message', 'Please select a bin first');
        }

        $code = $bin->barcode ?? $bin->bin_id;

        $this->barcodes = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $barcode = DNS1DFacade::getBarCodeSVG($code, 'C128', 2, 60, 'black', false);
            $this->barcodes[] = [
                'name' => $bin->bin_name ?? $bin->bin_id,
                'barcode' => $barcode,
                'code' => $code,
            ];
        }
    }

    public function getPdf()
    {
        $barcodes = [];

        $code = $this->bin->barcode ?? $this->bin->bin_id;

        for ($i = 1; $i <= $this->quantity; $i++) {
            $barcode = DNS1DFacade::getBarcodePNG(
                $code,
                'C128',
                2,
                60
            );
            $barcodes[] = $barcode;
        }

        $pdf = app(\App\Services\PdfGenerator::class)->make('bin::barcode.print', [
            'barcodes' => $barcodes,
            'price' => '',
            'name' => $this->bin->bin_name ?? '',
            'isPdf' => true,
        ], ['options' => ['encoding' => 'UTF-8']]);

        return $pdf->stream('barcodes-' . ($code ?? 'unknown') . '.pdf');
    }

    public function updatedQuantity() {
        $this->barcodes = [];
    }
}
