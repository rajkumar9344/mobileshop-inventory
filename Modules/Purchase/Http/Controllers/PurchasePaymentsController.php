<?php

namespace Modules\Purchase\Http\Controllers;

use Modules\Purchase\DataTables\PurchasePaymentsDataTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchasePayment;

class PurchasePaymentsController extends Controller
{

    public function index($purchase_id, PurchasePaymentsDataTable $dataTable) {
        abort_if(Gate::denies('access_purchase_payments'), 403);

        $purchase = Purchase::findOrFail($purchase_id);

        return $dataTable->render('purchase::payments.index', compact('purchase'));
    }


    public function create($purchase_id) {
        abort_if(Gate::denies('access_purchase_payments'), 403);

        $purchase = Purchase::findOrFail($purchase_id);

        return view('purchase::payments.create', compact('purchase'));
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_purchase_payments'), 403);

        $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:1000',
            'purchase_id' => 'required',
            'payment_method' => 'required|string|max:255'
        ]);

        DB::transaction(function () use ($request) {
            PurchasePayment::create([
                'date' => $request->date,
                'reference' => $request->reference,
                'amount' => $request->amount,
                'note' => $request->note,
                'purchase_id' => $request->purchase_id,
                'payment_method' => $request->payment_method
            ]);

            $purchase = Purchase::findOrFail($request->purchase_id);

            $due_amount = $purchase->due_amount - $request->amount;

            if ($due_amount == $purchase->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $purchase->update([
                'paid_amount' => $purchase->paid_amount + $request->amount,
                'due_amount' => $due_amount,
                'payment_status' => $payment_status
            ]);

            // Adjust supplier balance when payment is added to existing purchase
            if ($request->amount > 0) {
                $supplier = \Modules\People\Entities\Supplier::lockForUpdate()->find($purchase->supplier_id);
                if ($supplier) {
                    $supplier->open_balance = ($supplier->open_balance ?? 0) - $request->amount;
                    $supplier->save();
                }
            }
        });

        toast('Purchase Payment Created!', 'success');

        return redirect()->route('purchases.index');
    }


    public function edit($purchase_id, PurchasePayment $purchasePayment) {
        abort_if(Gate::denies('access_purchase_payments'), 403);

        $purchase = Purchase::findOrFail($purchase_id);

        return view('purchase::payments.edit', compact('purchasePayment', 'purchase'));
    }


    public function update(Request $request, PurchasePayment $purchasePayment) {
        abort_if(Gate::denies('access_purchase_payments'), 403);

        $request->validate([
            'date' => 'required|date',
            'reference' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'note' => 'nullable|string|max:1000',
            'purchase_id' => 'required',
            'payment_method' => 'required|string|max:255'
        ]);

        DB::transaction(function () use ($request, $purchasePayment) {
            $purchase = $purchasePayment->purchase;

            $due_amount = ($purchase->due_amount + $purchasePayment->amount) - $request->amount;

            if ($due_amount == $purchase->total_amount) {
                $payment_status = 'Unpaid';
            } elseif ($due_amount > 0) {
                $payment_status = 'Partial';
            } else {
                $payment_status = 'Paid';
            }

            $purchase->update([
                'paid_amount' => ($purchase->paid_amount - $purchasePayment->amount) + $request->amount,
                'due_amount' => $due_amount,
                'payment_status' => $payment_status
            ]);

            $purchasePayment->update([
                'date' => $request->date,
                'reference' => $request->reference,
                'amount' => $request->amount,
                'note' => $request->note,
                'purchase_id' => $request->purchase_id,
                'payment_method' => $request->payment_method
            ]);

            // Adjust supplier balance for the change in payment amount
            $amountDifference = $request->amount - $purchasePayment->amount;
            if (abs($amountDifference) > 0.01) {
                $supplier = \Modules\People\Entities\Supplier::lockForUpdate()->find($purchase->supplier_id);
                if ($supplier) {
                    $supplier->open_balance = ($supplier->open_balance ?? 0) - $amountDifference;
                    $supplier->save();
                }
            }
        });

        toast('Purchase Payment Updated!', 'info');

        return redirect()->route('purchases.index');
    }


    public function destroy(PurchasePayment $purchasePayment) {
        abort_if(Gate::denies('access_purchase_payments'), 403);

        DB::transaction(function () use ($purchasePayment) {
            $purchase = $purchasePayment->purchase;
            $paymentAmount = $purchasePayment->amount;

            // Adjust supplier balance when payment is deleted (add back the amount)
            if ($paymentAmount > 0) {
                $supplier = \Modules\People\Entities\Supplier::lockForUpdate()->find($purchase->supplier_id);
                if ($supplier) {
                    $supplier->open_balance = ($supplier->open_balance ?? 0) + $paymentAmount;
                    $supplier->save();
                }
            }

            // Update purchase due amount
            $purchase->update([
                'paid_amount' => $purchase->paid_amount - $paymentAmount,
                'due_amount' => $purchase->due_amount + $paymentAmount,
                'payment_status' => $purchase->due_amount + $paymentAmount > 0 ? 'Partial' : 'Unpaid'
            ]);

            $purchasePayment->delete();
        });

        toast('Purchase Payment Deleted!', 'warning');

        return redirect()->route('purchases.index');
    }
}
