<?php

namespace Modules\Bin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BarcodeController extends Controller
{
    public function printBarcode(Request $request)
    {
        abort_if(Gate::denies('print_bin_barcodes'), 403);

        // Render Livewire-driven page for bin barcode printing
        return view('bin::barcode.index');
    }
}
