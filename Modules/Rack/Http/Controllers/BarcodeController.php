<?php

namespace Modules\Rack\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BarcodeController extends Controller
{
    public function printBarcode(Request $request)
    {
        abort_if(Gate::denies('print_rack_barcodes'), 403);

        // Render Livewire-driven page for rack barcode printing
        return view('rack::barcode.index');
    }
}
