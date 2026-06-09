<?php

namespace Modules\Adjustment\Http\Controllers;

use Modules\Adjustment\DataTables\AdjustmentsDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Adjustment\Entities\AdjustedProduct;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Product\Entities\Product;

class AdjustmentController extends Controller
{

    public function index(AdjustmentsDataTable $dataTable) {
        abort_if(Gate::denies('access_adjustments'), 403);

        return $dataTable->render('adjustment::index');
    }


    public function create() {
        abort_if(Gate::denies('create_adjustments'), 403);

        return view('adjustment::create');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('create_adjustments'), 403);

        $request->validate([
            'reference'     => 'required|string|max:255',
            'date'          => 'required|date',
            'note'          => 'nullable|string|max:1000',
            'product_ids'   => 'required|array',
            'product_ids.*' => 'required|integer',
            'quantities'    => 'required|array',
            'quantities.*'  => 'required|integer|min:1|max:99999',
            'types'         => 'required|array',
            'types.*'       => 'required|in:add,sub'
        ]);

        DB::transaction(function () use ($request) {
            $adjustment = Adjustment::create([
                'date' => $request->date,
                'note' => $request->note
            ]);

            foreach ($request->product_ids as $key => $id) {
                $product = Product::findOrFail($id);

                // snapshot open quantity before change
                $openNow = (int) $product->open_quantity;
                $qty = (int) $request->quantities[$key];
                $type = $request->types[$key];
                $openAfter = ($type === 'sub') ? ($openNow - $qty) : ($openNow + $qty);

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $id,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'open_now'      => $openNow,
                    'open_after'    => $openAfter
                ]);

                if ($type == 'add') {
                    $product->update([
                        'open_quantity' => $openAfter
                    ]);
                } elseif ($type == 'sub') {
                    $product->update([
                        'open_quantity' => $openAfter
                    ]);
                }

                $product->recalculateProductQuantity();
            }
        });

        toast('Adjustment Created!', 'success');

        return redirect()->route('adjustments.index');
    }


    public function show(Adjustment $adjustment) {
        abort_if(Gate::denies('show_adjustments'), 403);

        return view('adjustment::show', compact('adjustment'));
    }


    public function edit(Adjustment $adjustment) {
        abort_if(Gate::denies('edit_adjustments'), 403);

        return view('adjustment::edit', compact('adjustment'));
    }


    public function update(Request $request, Adjustment $adjustment) {
        abort_if(Gate::denies('edit_adjustments'), 403);

        $request->validate([
            'reference'     => 'required|string|max:255',
            'date'          => 'required|date',
            'note'          => 'nullable|string|max:1000',
            'product_ids'   => 'required|array',
            'product_ids.*' => 'required|integer',
            'quantities'    => 'required|array',
            'quantities.*'  => 'required|integer|min:1|max:99999',
            'types'         => 'required|array',
            'types.*'       => 'required|in:add,sub'
        ]);

        DB::transaction(function () use ($request, $adjustment) {
            $adjustment->update([
                'reference' => $request->reference,
                'date'      => $request->date,
                'note'      => $request->note
            ]);

            foreach ($adjustment->adjustedProducts as $adjustedProduct) {
                // Prefer product_id (foreign key) to avoid null relation errors
                $product = Product::find($adjustedProduct->product_id);

                if (!$product) {
                    // Orphaned adjusted product; remove and continue
                    try {
                        $adjustedProduct->delete();
                    } catch (\Exception $e) {
                        \Log::warning('Failed to delete orphaned adjustedProduct id=' . ($adjustedProduct->id ?? 'n/a') . ': ' . $e->getMessage());
                    }
                    continue;
                }

                if ($adjustedProduct->type == 'add') {
                    $newOpen = $product->open_quantity - $adjustedProduct->quantity;
                } elseif ($adjustedProduct->type == 'sub') {
                    $newOpen = $product->open_quantity + $adjustedProduct->quantity;
                } else {
                    $newOpen = $product->open_quantity;
                }

                // Prevent open_quantity from going negative
                $newOpen = max(0, (int) $newOpen);

                $product->update([
                    'open_quantity' => $newOpen
                ]);

                $product->recalculateProductQuantity();

                $adjustedProduct->delete();
            }

            foreach ($request->product_ids as $key => $id) {
                $product = Product::findOrFail($id);

                // snapshot open quantity before change
                $openNow = (int) $product->open_quantity;
                $qty = (int) $request->quantities[$key];
                $type = $request->types[$key];
                $openAfter = ($type === 'sub') ? ($openNow - $qty) : ($openNow + $qty);

                AdjustedProduct::create([
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $id,
                    'quantity'      => $qty,
                    'type'          => $type,
                    'open_now'      => $openNow,
                    'open_after'    => $openAfter
                ]);

                if ($type == 'add') {
                    $product->update([
                        'open_quantity' => $openAfter
                    ]);
                } elseif ($type == 'sub') {
                    $product->update([
                        'open_quantity' => $openAfter
                    ]);
                }

                $product->recalculateProductQuantity();
            }
        });

        toast('Adjustment Updated!', 'info');

        return redirect()->route('adjustments.index');
    }


    public function destroy(Adjustment $adjustment) {
        abort_if(Gate::denies('delete_adjustments'), 403);

        DB::transaction(function () use ($adjustment) {
            foreach ($adjustment->adjustedProducts as $adjustedProduct) {
                $product = Product::find($adjustedProduct->product_id);

                if (!$product) {
                    // If product no longer exists, skip adjustment reversal
                    \Log::warning('Skipping adjustment reversal: product id ' . ($adjustedProduct->product_id ?? 'n/a') . ' not found for adjustedProduct id ' . ($adjustedProduct->id ?? 'n/a'));
                    continue;
                }

                if ($adjustedProduct->type == 'add') {
                    $newOpen = $product->open_quantity - $adjustedProduct->quantity;
                } elseif ($adjustedProduct->type == 'sub') {
                    $newOpen = $product->open_quantity + $adjustedProduct->quantity;
                } else {
                    $newOpen = $product->open_quantity;
                }

                // Prevent open_quantity from going negative
                $newOpen = max(0, (int) $newOpen);

                $product->update([
                    'open_quantity' => $newOpen
                ]);

                $product->recalculateProductQuantity();
            }

            $adjustment->delete();
        });

        toast('Adjustment Deleted!', 'warning');

        return redirect()->route('adjustments.index');
    }
}
