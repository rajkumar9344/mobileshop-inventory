<?php

namespace App\Livewire\Barcode;

use Livewire\Component;
use Milon\Barcode\Facades\DNS1DFacade;
use Modules\Rack\Entities\Rack;
use Barryvdh\DomPDF\Facade\Pdf;

class RackTable extends Component
{
    public $rack;
    public $quantity;
    public $barcodes;

    protected $listeners = ['rackSelected'];

    public function mount() {
        $this->rack = '';
        $this->quantity = 0;
        $this->barcodes = [];
    }

    public function render() {
        return view('livewire.barcode.rack-table');
    }

    public function rackSelected(Rack $rack) {
        $this->rack = $rack;
        $this->quantity = 1;
        $this->barcodes = [];
    }

    public function generateBarcodes()
    {
        // Use component state for rack and quantity to avoid stale/blade-evaluated params
        $quantity = (int) $this->quantity;
        if ($quantity <= 0) {
            return session()->flash('message', 'Please enter a valid quantity');
        }

        if ($quantity > 100) {
            return session()->flash('message', 'Max quantity is 100 per barcode generation!');
        }

        $rack = $this->rack;
        if (empty($rack) || !($rack instanceof Rack)) {
            return session()->flash('message', 'Please select a rack first');
        }

        $code = $rack->barcode ?? $rack->rack_id;

        $this->barcodes = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $barcode = DNS1DFacade::getBarCodeSVG($code, 'C128', 2, 60, 'black', false);
            $this->barcodes[] = [
                'name' => $rack->rack_name ?? $rack->rack_id,
                'barcode' => $barcode,
                'code' => $code,
            ];
        }
    }

    public function getPdf()
    {
        $barcodes = [];

        $code = $this->rack->barcode ?? $this->rack->rack_id;

        for ($i = 1; $i <= $this->quantity; $i++) {
            $barcode = DNS1DFacade::getBarcodePNG(
                $code,
                'C128',
                2,
                60
            );
            $barcodes[] = $barcode;
        }

        $pdf = app(\App\Services\PdfGenerator::class)->make('rack::barcode.print', [
            'barcodes' => $barcodes,
            'price' => '',
            'name' => $this->rack->rack_name ?? '',
            'isPdf' => true,
        ], ['options' => ['encoding' => 'UTF-8']]);

        return $pdf->stream('barcodes-' . ($code ?? 'unknown') . '.pdf');
    }

    public function updatedQuantity() {
        $this->barcodes = [];
    }
}
