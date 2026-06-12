<?php

namespace Modules\Reports\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ReportsController extends Controller
{

    public function reorderReport() {
        abort_if(Gate::denies('access_reports'), 403);

        return view('reports::stock.index');
    }

    public function currentStockReport() {
        abort_if(Gate::denies('access_reports'), 403);

        return view('reports::current-stock.index');
    }

    public function customersPaymentReport() {
        abort_if(Gate::denies('access_reports'), 403);

        return view('reports::customers-payment.index');
    }

    public function gstrReport() {
        abort_if(Gate::denies('access_reports'), 403);

        return view('reports::gstr.index');
    }

    public function dailyOperationsReport() {
        abort_if(Gate::denies('access_reports'), 403);

        return view('reports::daily-operations.index');
    }

    public function profitLossReport() {
        abort_if(Gate::denies('access_reports'), 403);

        return view('reports::profit-loss.index');
    }
}
