<?php

namespace Modules\Sale\Http\Controllers;

use Modules\Sale\DataTables\SalePaymentsDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SalePayment;
use Modules\Sale\Events\SaleFullyPaid;
use Illuminate\Support\Facades\Event;

class SalePaymentsController extends Controller
{

    public function index($sale_id, SalePaymentsDataTable $dataTable) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $sale = Sale::findOrFail($sale_id);

        return $dataTable->render('sale::payments.index', compact('sale'));
    }


    public function create($sale_id) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $sale = Sale::findOrFail($sale_id);

        return view('sale::payments.create', compact('sale'));
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:1000',
            'sale_id' => 'required',
            'payment_method' => 'nullable|string|max:255'
        ]);

    $shouldDispatch = false;
    $dispatchSaleId = null;

    DB::transaction(function () use ($request, &$shouldDispatch, &$dispatchSaleId) {
            SalePayment::create([
                'date' => $request->date,
                'reference' => $request->reference,
                'amount' => $request->amount,
                'note' => $request->note,
                'sale_id' => $request->sale_id,
                'payment_method' => $request->payment_method
            ]);

            $sale = Sale::findOrFail($request->sale_id);

            $prevStatus = $sale->payment_status;

            // Compute payable amount taking any stored discount into account
            $payable = ($sale->total_amount ?? 0) - ($sale->discount_amount ?? 0);

            $due_amount = $sale->due_amount - $request->amount;

            if ($due_amount == $payable) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $sale->update([
                'paid_amount' => ($sale->paid_amount + $request->amount),
                'due_amount' => $due_amount,
                'payment_status' => $payment_status
            ]);

            // If the sale became fully paid, mark for dispatch after commit.
            if ($payment_status === 'Paid' && $prevStatus !== 'Paid') {
                $shouldDispatch = true;
                $dispatchSaleId = $sale->id;
            }
        });

        // Dispatch the event after the DB transaction has completed.
        if ($shouldDispatch && $dispatchSaleId) {
            event(new SaleFullyPaid($dispatchSaleId));
        }

        toast('Sale Payment Created!', 'success');

        return redirect()->route('sales.index');
    }


    public function edit($sale_id, SalePayment $salePayment) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $sale = Sale::findOrFail($sale_id);

        return view('sale::payments.edit', compact('salePayment', 'sale'));
    }


    public function update(Request $request, SalePayment $salePayment) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:1000',
            'sale_id' => 'required',
            'payment_method' => 'nullable|string|max:255'
        ]);

        $shouldDispatch = false;
        $dispatchSaleId = null;

        DB::transaction(function () use ($request, $salePayment, &$shouldDispatch, &$dispatchSaleId) {
            $sale = $salePayment->sale;

            $prevStatus = $sale->payment_status;

            // Consider stored discount when evaluating resulting payment status
            $payable = ($sale->total_amount ?? 0) - ($sale->discount_amount ?? 0);

            $due_amount = ($sale->due_amount + $salePayment->amount) - $request->amount;

            if ($due_amount == $payable) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $sale->update([
                'paid_amount' => (($sale->paid_amount - $salePayment->amount) + $request->amount),
                'due_amount' => $due_amount,
                'payment_status' => $payment_status
            ]);

            // If the sale became fully paid as a result of this update, mark for dispatch after commit.
            if ($payment_status === 'Paid' && $prevStatus !== 'Paid') {
                $shouldDispatch = true;
                $dispatchSaleId = $sale->id;
            }

            $salePayment->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'amount' => $request->amount,
                'note' => $request->note,
                'sale_id' => $request->sale_id,
                'payment_method' => $request->payment_method
            ]);
        });

        if ($shouldDispatch && $dispatchSaleId) {
            event(new SaleFullyPaid($dispatchSaleId));
        }

        toast('Sale Payment Updated!', 'info');

        return redirect()->route('sales.index');
    }


    public function destroy(SalePayment $salePayment) {
        abort_if(Gate::denies('access_sale_payments'), 403);

        $salePayment->delete();

        toast('Sale Payment Deleted!', 'warning');

        return redirect()->route('sales.index');
    }
}
