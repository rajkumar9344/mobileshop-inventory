<?php

namespace App\Livewire;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Modules\Product\Entities\Product;

class ProductCart extends Component
{
    protected $listeners = ['productSelected', 'discountModalRefresh', 'applyCustomerAdditionalDiscount' => 'applyCustomerAdditionalDiscount', 'applyCustomerCashDiscount' => 'applyCustomerCashDiscount', 'purchaseTypeChanged' => 'setPurchaseType'];

    public $cart_instance;
    public $purchase_type = 1; // 1/2/3/4 — type 4 hides GST column and uses pre-tax totals
    public $readonly = false;
    public $global_discount_amount;
    public $global_tax_amount;
    public $shipping;
    public $adjustment;
    public $overall_other;
    public $quantity;
    public $check_quantity;
    public $has_invalid_quantity = false;
    public $invalid_row_ids = [];
    public $validation_message = '';
    public $discount_type = [];
    public $item_discount = [];
    public $unit_price;
    public $cash_discount_percent = [];
    public $cash_discount_amount = [];
    public $rate = [];
    public $rate_type = []; // N/M/L selector for purchase and purchase_return modules
    public $mrp = []; // editable MRP (price with tax)
    public $tax_percent_edit = []; // editable tax % per product (used for Rate before Discount)
    public $gst_percent = [];       // editable GST % per product (used for Amount incl. GST)
    public $data;
    public $original_sale_quantity = []; // Stores the original qty from the saved sale (edit mode only)
    public $customer_discount_percent = 0;
    public $customer_additional_discount = 0;

    // Multiple-codes support: keyed by product_id
    public $product_codes  = []; // all available codes per product
    public $selected_code  = []; // the currently selected code per product
    public $custom_product_names = []; // editable names for silicon mobile cover items (keyed by rowId)
    // Cache product master buy_price to avoid repeated DB queries during render
    protected $productBuyPriceCache = [];

    public function updatedCustomProductNames($value, $rowId) {
        if ($this->readonly || trim($value) === '') {
            return;
        }
        try {
            $this->cart()->update($rowId, ['name' => trim($value)]);
            $this->invalidateCache();
            $this->updateValidity();
        } catch (\Throwable $e) {
            // Row may not exist yet — ignore
        }
    }

    public function updatedCustomerAdditionalDiscount($value) {
        // Keep this as a default-for-new-items value only.
        // Do not auto-apply to existing cart rows here, otherwise edit-mode
        // saved discounts get overwritten by supplier defaults after load.
        $this->customer_additional_discount = is_numeric($value) ? (float) $value : 0;
        // Emit initial validity state for client scripts
        $this->updateValidity();
    }

    private $updateLock = false;
    private $cartItemsCache = null;
    private $cartItemsCacheTime = 0;
    const CACHE_DURATION = 30; // Cache duration in seconds
    private const PURCHASE_EDITABLE_INSTANCES = ['purchase', 'purchase_edit', 'purchase_return'];
    private const PURCHASE_ALL_INSTANCES = ['purchase', 'purchase_edit', 'purchase_return', 'purchase_view', 'purchase_return_view'];
    // Sale-related flows (sale, sale return, quotation) — share the BRD "sale group" layout.
    private const SALE_GROUP_INSTANCES = ['sale', 'sale_edit', 'sale_view', 'sale_return', 'sale_return_view', 'quotation', 'quotation_edit', 'quotation_view'];

    /**
     * True when the current cart instance is a sale-related flow (sale / sale return / quotation).
     * Single source of truth — use instead of hand-copied in_array() lists.
     */
    public function isSaleGroup(): bool
    {
        return in_array($this->cart_instance, self::SALE_GROUP_INSTANCES, true);
    }

    /**
     * True when the current cart instance is a purchase flow (purchase / purchase return).
     */
    public function isPurchaseGroup(): bool
    {
        return in_array($this->cart_instance, self::PURCHASE_ALL_INSTANCES, true);
    }

    /**
     * Return the shopping cart instance for the configured cart_instance.
     */
    private function cart()
    {
        return Cart::instance($this->cart_instance);
    }

    /**
     * Return true when current cart instance belongs to purchase flows.
     * Editable mode excludes readonly view instances by default.
     */
    private function isPurchaseCartInstance(bool $includeView = false): bool
    {
        $instances = $includeView ? self::PURCHASE_ALL_INSTANCES : self::PURCHASE_EDITABLE_INSTANCES;
        return in_array($this->cart_instance, $instances, true);
    }

    /**
     * Log a warning when the session grows abnormally large.
     * Serialisation is O(session size) so only run this in debug mode.
     */
    private function checkSessionSize()
    {
        if (!config('app.debug')) {
            return;
        }
        $bytes    = strlen(serialize(session()->all()));
        $limitMB  = 10;
        if ($bytes > $limitMB * 1024 * 1024) {
            Log::warning(sprintf(
                'Large session (%.2f MB) for cart instance: %s',
                $bytes / 1024 / 1024,
                $this->cart_instance
            ));
        }
    }

    /**
     * Execute a closure with update lock to prevent concurrent cart modifications
     */
    private function withUpdateLock($callback)
    {
        if ($this->updateLock) {
            return; // Skip if already locked
        }

        $this->updateLock = true;
        try {
            $callback();
        } finally {
            $this->updateLock = false;
        }
    }

    /**
     * Get cached cart items to reduce database queries
     */
    private function getCachedCartItems()
    {
        $now = microtime(true);
        if ($this->cartItemsCache === null || ($now - $this->cartItemsCacheTime) > self::CACHE_DURATION) {
            $this->cartItemsCache = $this->cart()->content();
            $this->cartItemsCacheTime = $now;
        }
        return $this->cartItemsCache;
    }

    /**
     * Find valid rowId for cart item, with fallback to product_id lookup
     */
    private function findValidRowId($cart, $row_id, $product_id)
    {
        // Single content() call — avoids fetching session data twice
        $content = $cart->content();

        if ($content->has($row_id)) {
            return $row_id;
        }

        // Fallback: find by product_id
        $item = $content->firstWhere('id', $product_id);
        
        if ($item) {
            // Only log if we're in debug mode to reduce log noise
            if (config('app.debug')) {
                Log::info("Cart rowId updated: {$row_id} → {$item->rowId} for product {$product_id}");
            }
            return $item->rowId;
        }

        return null; // Item not found
    }

    /**
     * Custom cart update that doesn't remove items on zero quantity
     */
    private function safeUpdateQuantity($cart, $rowId, $quantity)
    {
        // Sanitize and validate quantity
        $quantity = preg_replace('/[^0-9]/', '', $quantity); // Remove non-numeric characters
        $quantity = (int) $quantity; // Convert to integer
        
        if ($quantity <= 0) {
            // Don't update if quantity is zero or negative
            return;
        }
        
        $cart->update($rowId, $quantity);
    }

    /**
     * Invalidate cart cache when items are modified
     */
    private function invalidateCartCache()
    {
        $this->cartItemsCache = null;
        $this->cartItemsCacheTime = 0;
    }

    public function mount($cartInstance, $data = null, $readonly = false) {
        $this->cart_instance = $cartInstance;
        $this->readonly = (bool) $readonly;

        // Monitor session size for performance
        $this->checkSessionSize();

        if ($data) {
            $this->data = $data;

            $this->purchase_type = (int) ($data->purchase_type ?? 1);
            $this->global_discount_amount = $data->discount_percentage;
            $this->global_tax_amount = $data->tax_percentage;
            $this->shipping = $data->shipping_amount;
            $this->adjustment = (float) ($data->overall_adj ?? $data->adjustment ?? 0);
            $this->overall_other = (float) ($data->overall_other ?? 0);

            $cart_items = $this->getCachedCartItems();

            // Build a map of purchase/purchase-return details by product_id when editing
            $detailsByProduct = collect([]);
            // Support both Purchase (purchaseDetails) and PurchaseReturn (purchaseReturnDetails)
            if (isset($this->data->purchaseDetails) || isset($this->data->purchaseReturnDetails)) {
                $details = $this->data->purchaseDetails ?? $this->data->purchaseReturnDetails;
                $detailsByProduct = $details->keyBy('product_id');
            }

            foreach ($cart_items as $cart_item) {
                $this->check_quantity[$cart_item->id] = $cart_item->options->stock;
                $this->quantity[$cart_item->id] = $cart_item->qty;
                $this->original_sale_quantity[$cart_item->id] = $cart_item->qty; // freeze original qty for stock validation
                $this->unit_price[$cart_item->id] = $cart_item->price;

                // Set rate first so it can be used for discount calculation
                $taxPercent = $cart_item->options->tax_percent ?? 0;
                $original_mrp = $cart_item->options->mrp ?? $cart_item->price;
                $this->mrp[$cart_item->id] = (float)$original_mrp;
                // Prefer authoritative rate from existing purchase details when editing.
                // This preserves user-entered values (e.g. type-3 custom rate different from MRP).
                if ($detailsByProduct->has($cart_item->id) && $detailsByProduct->get($cart_item->id)->rate_before_discount !== null) {
                    $this->rate[$cart_item->id] = (float) $detailsByProduct->get($cart_item->id)->rate_before_discount;
                } elseif (in_array($this->cart_instance, ['sale', 'sale_edit', 'sale_view', 'sale_return', 'sale_return_view', 'quotation', 'quotation_edit', 'quotation_view'])) {
                    // Sale / Sale Return / Quotation: the user-entered Unit Price lives in the cart options. options is a
                    // Gloudemans CartItemOptions (a Collection) — a (array) cast mangles its keys,
                    // so read via has()/get(). Without this the value is lost and the rate falls
                    // back to mrp/(1+tax%) (e.g. 250 → 238.1) on the edit/view page.
                    $storedRbd = $cart_item->options->has('rate_before_discount') ? $cart_item->options->get('rate_before_discount') : null;
                    $storedRt  = $cart_item->options->has('rate') ? $cart_item->options->get('rate') : null;
                    $this->rate[$cart_item->id] = (float) ($storedRbd ?? $storedRt ?? $original_mrp);
                } else {
                    // Use the stored rate_before_discount if available (including explicit zero),
                    // else fallback to stored rate (including zero), else fallback to MRP-derived calculation.
                    $storedRateBeforeDiscount = array_key_exists('rate_before_discount', (array)$cart_item->options) ? $cart_item->options->rate_before_discount : null;
                    $storedRate = array_key_exists('rate', (array)$cart_item->options) ? $cart_item->options->rate : null;
                    if ($storedRateBeforeDiscount !== null) {
                        $this->rate[$cart_item->id] = (float)$storedRateBeforeDiscount;
                    } elseif ($storedRate !== null) {
                        $this->rate[$cart_item->id] = (float)$storedRate;
                    } else {
                        // Special-case: when editing a Type-4 purchase and product master has no buy_price,
                        // do not derive rate from MRP — leave as zero so UI shows empty/zero rate.
                        if (intval($this->purchase_type ?? 0) === 4) {
                            // Use cached product buy price helper to avoid repeated DB queries
                            $prodBuy = $this->getProductBuyPrice($cart_item->id);
                            $this->rate[$cart_item->id] = ($prodBuy > 0) ? $prodBuy : 0.0;
                        } elseif (intval($this->purchase_type ?? 0) === 3) {
                            $this->rate[$cart_item->id] = (float) $original_mrp;
                        } else {
                            $this->rate[$cart_item->id] = round($original_mrp / (1 + ($taxPercent / 100)), 2);
                        }
                    }
                }

                $this->discount_type[$cart_item->id] = $cart_item->options->product_discount_type;
                if ($cart_item->options->product_discount_type == 'fixed') {
                    $this->item_discount[$cart_item->id] = $cart_item->options->product_discount;
                } elseif ($cart_item->options->product_discount_type == 'percentage') {
                    // Use stored percent directly; no recalculation needed
                    $this->item_discount[$cart_item->id] = $cart_item->options->product_discount_percent ?? 0;
                }

                $this->cash_discount_percent[$cart_item->id] = intval($cart_item->options->cash_discount_percent ?? 0);
                $this->cash_discount_amount[$cart_item->id] = $cart_item->options->cash_discount_amount ?? 0;
                $this->tax_percent_edit[$cart_item->id] = floatval($taxPercent);
                $this->gst_percent[$cart_item->id]      = floatval($cart_item->options->gst_percent ?? $taxPercent);
                $this->rate_type[$cart_item->id] = $cart_item->options->rate_type ?? 'M'; // Default to M (MRP)

                // Restore available codes (edit mode)
                $allCodes = \Modules\Product\Entities\ProductCode::where('product_id', $cart_item->id)
                    ->orderByDesc('is_primary')
                    ->pluck('code')
                    ->toArray();
                $savedCode = $cart_item->options->code ?? null;
                if (empty($allCodes)) {
                    $allCodes = $savedCode ? [$savedCode] : [];
                }
                $this->product_codes[$cart_item->id] = $allCodes;
                $this->selected_code[$cart_item->id] = $savedCode ?? ($allCodes[0] ?? '');

                // Pre-populate the editable product name for every item in edit mode
                $this->custom_product_names[$cart_item->rowId] = $cart_item->name;
            }
        } else {
            $this->purchase_type = 1;
            $this->global_discount_amount = 0;
            $this->global_tax_amount = 0;
            $this->shipping = 0.00;
            $this->adjustment = 0.00;
            $this->overall_other = 0.00;
            $this->customer_discount_percent = 0;
            $this->check_quantity = [];
            $this->quantity = [];
            $this->unit_price = [];
            $this->discount_type = [];
            $this->item_discount = [];
            $this->cash_discount_percent = [];
            $this->cash_discount_amount = [];
            $this->rate = [];
            $this->rate_type = []; // Initialize for new items
            $this->mrp = []; // Initialize editable MRP storage for new items
            $this->tax_percent_edit = [];
            $this->gst_percent = [];
            $this->product_codes = [];
            $this->selected_code = [];
            $this->custom_product_names = [];
        }

        // (debug logging removed)
    }

    public function render() {
        $cart_items = $this->cart_items;

        return view('livewire.product-cart', [
            'cart_items' => $cart_items
        ]);
    }

    /**
     * Return product master buy_price for given product id, caching the result.
     * Returns float(0) when not present.
     */
    public function getProductBuyPrice($productId)
    {
        if (array_key_exists($productId, $this->productBuyPriceCache)) {
            return $this->productBuyPriceCache[$productId];
        }

        try {
            $p = Product::find($productId);
            $val = floatval($p->buy_price ?? 0);
        } catch (\Exception $e) {
            $val = 0.0;
        }

        $this->productBuyPriceCache[$productId] = $val;
        return $val;
    }

    public function updatedRateType($value, $name) {
        if ($this->readonly) return;
        $this->withUpdateLock(function() use ($value, $name) {
            $id = str_replace('rate_type.', '', $name);

            if ($this->cart_instance !== 'purchase' && $this->cart_instance !== 'purchase_return' && $this->cart_instance !== 'sale') {
                return;
            }

            if (!in_array(strtoupper($value), ['N', 'M', 'L'])) {
                return;
            }

            $cart_items = $this->getCachedCartItems();
            $cart_item = $cart_items->firstWhere('id', $id);

            if (!$cart_item) {
                return;
            }

            $product = Product::find($id);
            if (!$product) {
                return;
            }

            $tax_percent = $cart_item->options->tax_percent ?? 0;
            $calculated_rate = 0;
            $skipTaxDivision = false;

            switch (strtoupper($value)) {
                case 'N': // Net Rate (Purchase/Purchase Return) or Sell Rate (Sale)
                    if ($this->isPurchaseCartInstance()) {
                        // When purchase type is 4, use product_cost directly as the pre-tax purchase rate
                        if ($this->purchase_type == 4) {
                            if (!is_null($product->product_cost)) {
                                $calculated_rate = $product->product_cost;
                                $skipTaxDivision = true;
                            } else {
                                $calculated_rate = $product->product_price ?? 0;
                            }
                        } else {
                            // Default: use sell rate (product_price) first
                            $calculated_rate = $product->product_price ?? $product->product_cost ?? 0;
                        }
                    } else {
                        $calculated_rate = $product->product_price ?? 0;
                    }
                    break;
                case 'M': // MRP
                    $calculated_rate = $product->mrp ?? 0;
                    break;
                case 'L': // List Price (Purchase) or Sell Rate (Sale)
                    // Prefer explicit list_price if present, then product_price, then mrp
                    $calculated_rate = $product->list_price ?? $product->product_price ?? $product->mrp ?? 0;
                    break;
            }

            if (
                !$skipTaxDivision
                && !(
                    $this->isPurchaseCartInstance()
                    && $this->purchase_type == 3
                    && strtoupper($value) === 'M'
                )
                && $tax_percent > 0
                && $calculated_rate > 0
            ) {
                $calculated_rate = $calculated_rate / (1 + ($tax_percent / 100));
            }

            // If calculated rate is zero (missing field), try sensible fallbacks
            if ($calculated_rate <= 0) {
                // If Net rate missing, try product_price, then mrp
                $calculated_rate = $product->product_price ?? $product->mrp ?? $product->product_cost ?? 0;
                if ($tax_percent > 0 && $calculated_rate > 0) {
                    $calculated_rate = $calculated_rate / (1 + ($tax_percent / 100));
                }

                if ($calculated_rate <= 0) {
                    return;
                }
            }

            // Update component properties (rate only — MRP is not auto-recalculated)
            $this->rate_type[$id] = strtoupper($value);
            $this->rate[$id] = (float)$calculated_rate;

            // Update rate_type in cart options but do NOT overwrite 'mrp'.
            try {
                $opts = $cart_item->options->toArray();
                $opts['rate_type'] = strtoupper($value);
                $this->cart()->update($cart_item->rowId, [
                    'options' => $opts
                ]);
            } catch (\Exception $e) {
                // ignore
            }

            // Recalculate price and options with discounts applied
            $this->updateItemPrice($cart_item->rowId);
            
            // Force Livewire to refresh the MRP display
            $this->dispatch('refreshCart');
        });
    }

    public function updatedMrp($value, $id)
    {
        if ($this->readonly) return;
        $this->withUpdateLock(function() use ($value, $id) {
            if (!is_numeric($value) || $value < 0) {
                return;
            }

            $cart_items = $this->getCachedCartItems();
            $cart_item = $cart_items->firstWhere('id', $id);
            if (!$cart_item) {
                return;
            }

            $tax_percent = floatval($cart_item->options->tax_percent ?? 0);
            $new_mrp     = floatval($value);

            // Guard: if MRP hasn't actually changed from the cart-session value, skip
            // recalculation to avoid overwriting a user-entered Rate before Discount
            // when the user merely focuses/blurs the MRP field without editing it.
            $existing_mrp = floatval($cart_item->options->mrp ?? 0);
            if (abs($new_mrp - $existing_mrp) < 0.005) {
                return;
            }

            // For Purchase contexts: when purchase_type is 1/2/3, editing MRP should
            // recompute the Rate before Discount = MRP / (1 + tax%) and update the
            // cart options accordingly. When purchase_type == 4, keep the old
            // behaviour where MRP is treated as a label and does not alter Rate.
            if ($this->isPurchaseCartInstance()) {
                $this->mrp[$id] = (float)$new_mrp;
                $opts = $cart_item->options->toArray();
                $opts['mrp'] = (float)$new_mrp;

                if ($this->purchase_type != 4) {
                    // For purchase type 3, Rate Before Discount mirrors MRP.
                    // For other purchase types, keep pre-tax derivation from MRP.
                    $new_rate = $this->purchase_type == 3
                        ? $new_mrp
                        : ($tax_percent > 0 ? $new_mrp / (1 + $tax_percent / 100) : $new_mrp);
                    $this->rate[$id] = round($new_rate, 2);
                    $this->rate_type[$id] = 'M';

                    // Persist both mrp and rate_before_discount into cart options
                    $opts['rate_before_discount'] = (float)$this->rate[$id];
                    $opts['rate'] = (float)$this->rate[$id];
                    $opts['rate_type'] = 'M';

                    $this->cart()->update($cart_item->rowId, ['options' => $opts]);
                    $this->invalidateCartCache();

                    // Recalculate item price (applies discounts & tax)
                    $this->updateItemPrice($cart_item->rowId);
                    // Ensure UI reflects refreshed values
                    $this->dispatch('refreshCart');
                    return;
                }

                // purchase_type == 4: persist only MRP (no rate recalculation)
                $this->cart()->update($cart_item->rowId, ['options' => $opts]);
                $this->invalidateCartCache();
                return;
            }

            // For other modules (Sale, Quotation etc.): derive rate from MRP and recalculate.
            $new_rate = $tax_percent > 0 ? $new_mrp / (1 + $tax_percent / 100) : $new_mrp;

            // Update component state so blade reads fresh values
            $this->mrp[$id]  = (float)$new_mrp;
            $this->rate[$id] = round($new_rate, 2);

            // Persist MRP to cart options; updateItemPrice handles everything else
            $opts        = $cart_item->options->toArray();
            $opts['mrp'] = (float)$new_mrp;
            $this->cart()->update($cart_item->rowId, ['options' => $opts]);
            $this->invalidateCartCache();

            // Full recalculation: discounts + GST applied correctly
            $this->updateItemPrice($cart_item->rowId);
        });
    }

    public function productSelected($product) {
        if ($this->readonly) return;
        $this->calculate($product);
    }

    public function calculate($product) {
        if ($this->readonly) return;
        $cart = $this->cart();

        // Convert product to array if it's an object
        if (is_object($product)) {
            $product = $product->toArray();
        }

        $exists = $cart->search(function ($cartItem, $rowId) use ($product) {
            return $cartItem->id == $product['id'];
        });

        if ($exists->isNotEmpty()) {
            session()->flash('message', 'Product exists in the cart!');
            return;
        }

        // Use the passed product data directly (it should include category relationship)
        $fullProduct = $product;
        $categoryName = $fullProduct['category_name'] ?? $fullProduct['category'] ?? '-';

        // Respect stock rules: prefer `stock` passed by SearchProduct (computed per context).
        $stock = null;
        if (isset($fullProduct['stock'])) {
            $stock = (int) $fullProduct['stock'];
        } else {
            if ($this->cart_instance === 'purchase_return') {
                $stock = isset($fullProduct['purchase_quantity']) ? (int) $fullProduct['purchase_quantity'] : (isset($fullProduct['product_quantity']) ? (int)$fullProduct['product_quantity'] : null);
            } else {
                $stock = isset($fullProduct['product_quantity']) ? (int) $fullProduct['product_quantity'] : null;
            }
        }

        // For sale and purchase_return contexts, block adds when there is no stock.
        if (in_array($this->cart_instance, ['sale', 'sale_edit', 'purchase_return']) && $stock !== null && $stock <= 0) {
            session()->flash('message', 'Stock is not available.');
            return;
        }

        // Calculate pricing based on rate type for purchase module
        $mrp = $fullProduct['mrp'] ?? $fullProduct['product_price'] ?? 0;
        $tax_percent = $fullProduct['product_order_tax'] ?? 0;

        // New product default rate by context (Purchase Bill Type removed — single path).
        if ($this->isPurchaseGroup()) {
            // Purchase / Purchase Return (BRD): Purchase Rate pre-fills with the product's
            // cost and VAT defaults to 5%. Both are editable.
            $rate        = (float) ($fullProduct['product_cost'] ?? 0);
            $base_rate   = $rate;
            $tax_percent = 5.0;
        } elseif ($this->isSaleGroup()) {
            // Sale / Sale Return / Quotation (BRD): unit price is manually entered
            // (blank); VAT% defaults to 5%. MRP is not auto-populated.
            $base_rate   = 0.0;
            $rate        = 0.0;
            $tax_percent = 5.0;
        } else {
            // Default: use MRP (price-with-tax) as base_rate
            $base_rate = $mrp;
            $rate = $base_rate / (1 + ($tax_percent / 100));
        }

        // Calculate tax and totals
        $product_tax = $base_rate * ($tax_percent / 100);
        $sub_total = $base_rate;

        // determine initial quantity from payload default_qty or fallback to 1
        $initialQty = isset($fullProduct['default_qty']) ? (int) $fullProduct['default_qty'] : 1;
        if ($initialQty <= 0) {
            session()->flash('message', 'Stock is not available.');
            return;
        }

        // Sale group (sale / sale return / quotation): mrp starts at 0 (unit price is
        // user-entered); other instances keep product MRP.
        $display_mrp = $this->isSaleGroup()
            ? 0.0
            : (array_key_exists('mrp', $fullProduct) && $fullProduct['mrp'] !== null ? (float)$fullProduct['mrp'] : (float)$base_rate);

        $newCartItem = $cart->add([
            'id'      => $fullProduct['id'],
            'name'    => $fullProduct['product_name'],
            'qty'     => $initialQty,
            'price'   => $base_rate,
            'weight'  => 1,
            'options' => [
                'product_discount'         => 0.00,
                'product_discount_type'    => 'percentage',
                // Apply any currently-set customer additional discount to newly added products
                'product_discount_percent' => (float) ($this->customer_additional_discount ?? 0),
                'sub_total'             => $sub_total,
                'code'                  => $fullProduct['product_code'],
                'stock'                 => $fullProduct['stock'] ?? ($this->cart_instance === 'purchase_return' ? ($fullProduct['purchase_quantity'] ?? $fullProduct['product_quantity'] ?? null) : ($fullProduct['product_quantity'] ?? null)),
                'unit'                  => $fullProduct['product_unit'] ?: 'Nos',
                'product_tax'           => $product_tax,
                'product_tax_per_unit'  => $product_tax,
                'unit_price'            => $rate,
                'category'              => $categoryName,
                'tax_percent'           => $tax_percent,
                'gst_percent'           => $tax_percent,
                // Store MRP as the product master MRP when present, otherwise fall back to base_rate
                'mrp'                   => $display_mrp,
                'rate'                  => (float)$rate,
                'cash_discount_percent' => $this->customer_discount_percent,
                // rate_before_discount and rate_type: when purchase_type==4 prefer product_cost (rate already set above)
                'rate_before_discount'  => (float)$rate,
                'rate_type'             => ($this->purchase_type == 4 ? 'N' : 'M'),
                'product_cost'          => (float)($fullProduct['product_cost'] ?? 0)
            ]
        ]);
        // Allow the product name to be edited for every added item
        $this->custom_product_names[$newCartItem->rowId] = $fullProduct['product_name'];

        $this->check_quantity[$fullProduct['id']] = $stock;
        $this->quantity[$fullProduct['id']] = $initialQty;
        $this->discount_type[$fullProduct['id']] = 'percentage'; // Default to percentage for Dis % column
        // Initialize per-item discount to the customer's additional discount so new items inherit it
        $this->item_discount[$fullProduct['id']] = (float) ($this->customer_additional_discount ?? 0);
        $this->rate[$fullProduct['id']] = (float)$rate;
        $this->cash_discount_percent[$fullProduct['id']] = $this->customer_discount_percent;
        $this->rate_type[$fullProduct['id']] = ($this->purchase_type == 4 ? 'N' : 'M');
        // Ensure editable MRP is set so the input shows the product master MRP when available
        $this->mrp[$fullProduct['id']] = $display_mrp;
        $this->tax_percent_edit[$fullProduct['id']] = floatval($tax_percent);
        $this->gst_percent[$fullProduct['id']]      = floatval($tax_percent);

        // Store available codes for the dropdown
        $codes = $fullProduct['product_codes'] ?? [$fullProduct['product_code']];
        $this->product_codes[$fullProduct['id']]  = $codes;
        $this->selected_code[$fullProduct['id']]  = $fullProduct['product_code'];

        $this->invalidateCartCache();
        $this->dispatch('refreshCart');
        $this->updateValidity();
        // Debug: log cart snapshot right after adding a product
        if (config('app.debug')) {
            try {
                $snapshot = $this->getCachedCartItems()->map(function($i){
                    return [
                        'rowId' => $i->rowId,
                        'id'    => $i->id,
                        'qty'   => $i->qty,
                        'stock' => $i->options->stock ?? null
                    ];
                })->toArray();
                Log::info('calculate cart snapshot', ['cart_instance' => $this->cart_instance, 'snapshot' => $snapshot]);
            } catch (\Exception $e) {
                // ignore logging errors
            }
        }
    }

    public function removeItem($row_id) {
        if ($this->readonly) return;
        $this->cart()->remove($row_id);
        $this->invalidateCartCache();
        $this->dispatch('refreshCart');
        $this->updateValidity();
    }

    /**
     * Called when the user selects a different code in the cart row dropdown.
     * Persists the selected code into the cart item options so it gets saved
     * in purchase_details / sales_details at transaction save time.
     */
    public function updatedSelectedCode($value, $id)
    {
        if ($this->readonly) return;

        $cart_items = $this->getCachedCartItems();
        $cart_item  = $cart_items->firstWhere('id', $id);
        if (!$cart_item) return;

        $opts         = $cart_item->options->toArray();
        $opts['code'] = $value;
        $this->cart()->update($cart_item->rowId, ['options' => $opts]);
        $this->invalidateCartCache();
    }

    public function updatedCashDiscountPercent($value, $id) {
        if ($this->readonly) return;

        // Normalise: empty / non-numeric / zero all treated as 0
        if ($value === '' || $value === null || !is_numeric($value) || floatval($value) <= 0) {
            $this->cash_discount_percent[$id] = 0;
        } elseif ($value > 999) {
            $this->cash_discount_percent[$id] = 999;
        } else {
            $this->cash_discount_percent[$id] = intval($value);
        }

        $cart_items = $this->getCachedCartItems();
        $cart_item  = $cart_items->firstWhere('id', $id);
        if (!$cart_item) return;

        $this->updateItemPrice($cart_item->rowId);
        $this->global_discount_amount = $this->computeGlobalEffectiveDiscount();
        // Re-evaluate cart validity after quantity changes
        $this->updateValidity();
    }
    public function updatedCashDiscountAmount($value, $id) {
        if ($this->readonly) return;

        // Normalise: empty / non-numeric / zero → 0
        if ($value === '' || $value === null || !is_numeric($value) || floatval($value) <= 0) {
            $this->cash_discount_amount[$id] = 0;
            $cart_items = $this->getCachedCartItems();
            $cart_item  = $cart_items->firstWhere('id', $id);
            if ($cart_item) {
                $this->updateItemPrice($cart_item->rowId);
                $this->global_discount_amount = $this->computeGlobalEffectiveDiscount();
            }
            return;
        }

        $cart_items = $this->getCachedCartItems();
        $cart_item  = $cart_items->firstWhere('id', $id);
        if (!$cart_item) return;

        $mrp = (float) ($cart_item->options->mrp ?? 0);

        // Cap amount to MRP so discount can never exceed the product price
        $this->cash_discount_amount[$id] = min((float)$value, $mrp);

        $this->updateItemPrice($cart_item->rowId);
        $this->global_discount_amount = $this->computeGlobalEffectiveDiscount();
    }

    public function updatedRate($value, $id) {
        if ($this->readonly) return;
        $this->withUpdateLock(function() use ($value, $id) {
            $cart_items = $this->getCachedCartItems();
            $cart_item  = $cart_items->firstWhere('id', $id);

            // Guard: compare against the cart-session's stored rate_before_discount
            // rather than $this->rate[$id] (which Livewire pre-sets to $value before
            // calling this hook, making that comparison always true / always early-return).
            if ($cart_item) {
                $existing_rate = floatval($cart_item->options->rate_before_discount ?? 0);
                if (abs(floatval($value) - $existing_rate) < 0.005) return;
            }

            if (!is_numeric($value) || $value < 0) {
                if ($cart_item) {
                    $this->rate[$id] = round($cart_item->price / (1 + (($cart_item->options->tax_percent ?? 0) / 100)), 2);
                }
                return;
            }

            if (!$cart_item) {
                return;
            }

            $value = (float)$value;
            $this->rate[$id] = $value;

            // Persist rate_before_discount to cart options
            $opts = $cart_item->options->toArray();
            $opts['rate_before_discount'] = $value;
            // For sale-group instances: keep mrp in sync with the user-entered unit price so
            // the overall totals AND the saved rate (CartItemCalculator reads options->mrp)
            // match the per-row Amount display.
            if (in_array($this->cart_instance, ['sale', 'sale_edit', 'sale_return', 'sale_return_view', 'quotation', 'quotation_edit', 'quotation_view'])) {
                $opts['mrp'] = $value;
                $this->mrp[$id] = $value;
            }
            $this->cart()->update($cart_item->rowId, ['options' => $opts]);
            $this->invalidateCartCache();

            // Recalculate price and options with discounts applied
            $this->updateItemPrice($cart_item->rowId);
        });
    }

    public function updatedGstPercent($value, $id) {
        if ($this->readonly) return;
        if (!is_numeric($value) || $value < 0) return;

        $cart_items = $this->getCachedCartItems();
        $cart_item = $cart_items->firstWhere('id', $id);
        if (!$cart_item) return;

        // Save new GST % to cart options
        $opts = $cart_item->options->toArray();
        $opts['gst_percent'] = (float) $value;
        $this->cart()->update($cart_item->rowId, ['options' => $opts]);
        $this->invalidateCartCache();

        // Recalculate final price under new GST %
        $this->updateItemPrice($cart_item->rowId);
    }

    public function updatedTaxPercentEdit($value, $id) {
        if ($this->readonly) return;
        if (!is_numeric($value) || $value < 0) return;

        $cart_items = $this->getCachedCartItems();
        $cart_item = $cart_items->firstWhere('id', $id);
        if (!$cart_item) return;

        $tax_pct = (float) $value;

        // Persist new tax_percent into cart options
        $opts = $cart_item->options->toArray();
        $opts['tax_percent'] = $tax_pct;
        $this->cart()->update($cart_item->rowId, [
            'options' => $opts
        ]);
        $this->invalidateCartCache();

        // Recompute pre-tax rate from unchanged MRP
        $mrp = floatval($this->mrp[$id] ?? $cart_item->options->mrp ?? $cart_item->price);
        $new_rate = (
            $this->isPurchaseCartInstance()
            && $this->purchase_type == 3
        )
            ? $mrp
            : ($tax_pct > 0 ? $mrp / (1 + $tax_pct / 100) : $mrp);
        $this->rate[$id] = (float)$new_rate;

        // Recalculate price with discounts under new tax rate
        $this->updateItemPrice($cart_item->rowId);
    }

    public function updatedItemDiscount($value, $id) {
        if ($this->readonly) return;
        $cart_items = $this->getCachedCartItems();
        $cart_item = $cart_items->firstWhere('id', $id);
        if ($cart_item) {
            $this->updateItemPrice($cart_item->rowId);
        }
    }

    protected function updateItemPrice($row_id) {
        // Invalidate cache first to ensure fresh data
        $this->invalidateCartCache();
        
        $cart_item = $this->cart()->get($row_id);
        if (!$cart_item) {
            return; // Row no longer exists (e.g. removed between updates)
        }
        $product_id = $cart_item->id;
        
        $tax_percent = floatval($cart_item->options->tax_percent ?? 0);
        // GST % used for the final Amount incl. GST — independent from tax_percent
        $gst_pct = floatval($this->gst_percent[$product_id] ?? $cart_item->options->gst_percent ?? $tax_percent);

        // MRP is always the stored value — never auto-derived from rate.
        $base_mrp = floatval($cart_item->options->mrp ?? $cart_item->price);

        // ── All calculations in pre-tax space ────────────────────────────────
        // Discounts removed from all transaction modules:
        //   net_rate(pre-tax) = rate (as entered)
        //   final_price_with_tax = net_rate × (1 + VAT%)
        // ─────────────────────────────────────────────────────────────────────

        // Pre-tax base rate (Rate column):
        // Treat an explicit rate of 0 as an explicit user value and DO NOT
        // fall back to a previously stored value or MRP-derived rate.
        $hasExplicitRate = is_array($this->rate) ? array_key_exists($product_id, $this->rate) : isset($this->rate[$product_id]);
        if ($hasExplicitRate) {
            $rate_before_discount = floatval($this->rate[$product_id]);
        } elseif (isset($cart_item->options->rate_before_discount)) {
            $rate_before_discount = floatval($cart_item->options->rate_before_discount);
        } else {
            $rate_before_discount = ($tax_percent > 0 ? $base_mrp / (1 + $tax_percent / 100) : $base_mrp);
        }

        // No discounts — net pre-tax rate is the rate as entered.
        $net_rate_pre_tax = max(0.0, $rate_before_discount);

        // Convert pre-tax net rate to final price-with-tax using GST % (independent column)
        $final_price_with_tax = $net_rate_pre_tax * (1 + ($gst_pct / 100));
        $final_rate = $net_rate_pre_tax; // pre-tax (= "Net Rate" column)
        // Do NOT overwrite $this->rate here — it is set by user-facing updaters
        // (updatedRate, updatedMrp, updatedRateType, updatedTaxPercentEdit) and must
        // remain stable so that "Rate before Discount" doesn't reset on every recalc.

        // Tax amount per unit = final_price_with_tax - pre_tax_rate
        $tax_amount_per_unit = $final_price_with_tax - $final_rate;
        $total_tax_amount = $tax_amount_per_unit * $cart_item->qty;

        // Subtotal = final_price_with_tax × qty
        $sub_total = $final_price_with_tax * $cart_item->qty;

        // Update price and options — 'mrp' is intentionally excluded so it is never overwritten here.
        $this->cart()->update($row_id, [
            'price' => (float)$final_price_with_tax,
            'options' => array_merge($cart_item->options->toArray(), [
                'sub_total'             => (float)$sub_total,
                'product_tax'           => (float)$total_tax_amount,
                'product_tax_per_unit'  => (float)$tax_amount_per_unit,
                'unit_price'            => (float)$final_price_with_tax,
                'gst_percent'           => $gst_pct,
                // Discounts removed: keep zero-effect, non-null defaults so legacy
                // return/quotation detail tables (NOT NULL columns) still persist cleanly.
                'cash_discount_percent' => 0,
                'cash_discount_amount'  => 0,
                'product_discount'      => 0,
                'product_discount_type' => 'percentage',
                'product_discount_percent' => 0,
                'rate_before_discount'  => (float)$rate_before_discount,
                'rate'                  => (float)$final_rate,
                'amount'                => (float)$sub_total,
                'tax_amount'            => (float)$total_tax_amount
                // 'mrp' is intentionally NOT here — it is never auto-updated during recalculation
            ])
        ]);
        
        // Force cache invalidation after update
        $this->invalidateCartCache();
    }

    protected function getUpdatedCartOptions($cart_item, $row_id, $product_id = null, $discount_amount = 0) {
        $current_price = $cart_item->price;
        $tax_percent = $cart_item->options->tax_percent ?? 0;
        $product_id = $product_id ?? $cart_item->id;
        $rate = $this->rate[$product_id] ?? ($current_price / (1 + ($tax_percent / 100)));
        // Preserve explicit pre-discount rate if present; fall back to computed rate
        $rate_before_discount = $this->rate[$product_id] ?? ($cart_item->options->rate_before_discount ?? $rate);

        return [
            'sub_total'                => $current_price * $cart_item->qty,
            'code'                     => $cart_item->options->code,
            'stock'                    => $cart_item->options->stock,
            'unit'                     => $cart_item->options->unit,
            'product_tax'              => ($current_price - $rate) * $cart_item->qty,
            'product_tax_per_unit'     => ($current_price - $rate),
            'unit_price'               => $current_price,
            'product_discount'         => $discount_amount,
            'product_discount_type'    => $this->discount_type[$product_id] ?? $cart_item->options->product_discount_type ?? 'fixed',
            'product_discount_percent' => (float) ($cart_item->options->product_discount_percent ?? 0),
            'gst_percent'              => $cart_item->options->gst_percent ?? $tax_percent,
            'category'                 => $cart_item->options->category ?? '-',
            'tax_percent'              => $tax_percent,
            'mrp'                      => $cart_item->options->mrp ?? $current_price,
            'rate_before_discount'     => $rate_before_discount,
            'cash_discount_percent'    => $this->cash_discount_percent[$product_id] ?? 0,
            'cash_discount_amount'     => $this->cash_discount_amount[$product_id] ?? 0,
            'rate'                     => $rate
        ];
    }

    /**
     * Return the effective cash discount amount for an item: if a percent is set (>0)
     * use percent * mrp / 100, otherwise use the explicit cash_discount_amount.
     */
    private function getEffectiveCashDiscountAmount($product_id, $base_price)
    {
        $percent = $this->cash_discount_percent[$product_id] ?? 0;
        $amount  = $this->cash_discount_amount[$product_id]  ?? 0;

        // Both percent-based and fixed amounts combine (either can be zero)
        $percentAmt = (is_numeric($percent) && $percent > 0) ? ($base_price * (float)$percent / 100.0) : 0.0;
        $amountVal  = (is_numeric($amount)  && $amount  > 0) ? (float)$amount : 0.0;

        return $percentAmt + $amountVal;
    }

    /**
     * Return true when the current mounted data represents a draft document.
     * Draft mode should behave like a create page (do NOT add back original sale qty).
     */
    private function isDraftMode()
    {
        if (!$this->data) return false;
        $status = null;
        if (is_array($this->data)) {
            $status = $this->data['status'] ?? null;
        } else {
            $status = $this->data->status ?? null;
        }
        return is_string($status) && strtolower(trim($status)) === 'draft';
    }

    /**
     * Return true when the component should behave like a create page
     * (i.e. do NOT add back original saved qty). This is true when the
     * document is a draft OR when the mounted data includes `create_receipt` set.
     */
    private function shouldTreatAsCreate()
    {
        if ($this->isDraftMode()) return true;
        if (!$this->data) return false;

        // Prefer explicit `create_receipt` flag passed from purchase return forms
        if (is_array($this->data) && array_key_exists('create_receipt', $this->data)) {
            $val = $this->data['create_receipt'];
            return !($val === 1 || $val === '1' || $val === true);
        }
        if (!is_array($this->data) && isset($this->data->create_receipt)) {
            $val = $this->data->create_receipt;
            return !($val === 1 || $val === '1' || $val === true);
        }

        return false;
    }

    /**
     * Compute the global effective discount as the sum of effective discounts for all cart items.
     * Optimized for large carts using cached data.
     */
    private function computeGlobalEffectiveDiscount()
    {
        $total = 0;
        $cart_items = $this->getCachedCartItems();

        // Use array operations for better performance with large datasets
        foreach ($cart_items as $item) {
            $product_id = $item->id;
            $mrp = $item->options->mrp ?? $item->price;
            $perUnitDiscount = $this->getEffectiveCashDiscountAmount($product_id, $mrp);
            $total += $perUnitDiscount * $item->qty;
        }

        return (float)$total;
    }

    /**
     * Compute whether any cart item quantity exceeds its available/purchased stock
     * and emit a Livewire event so pages can disable the submit button.
     */
    private function updateValidity()
    {
        $invalidIds = [];
        $debugSnapshot = [];
        try {
            $cart_items = $this->getCachedCartItems();
            foreach ($cart_items as $item) {
                $product_id = $item->id;
                $currentStock = (int) ($item->options->stock ?? (isset($this->check_quantity[$product_id]) ? $this->check_quantity[$product_id] : 0));

                // By default use DB stock. When editing an existing document
                // that reduces stock on save we add back the original saved qty
                // so the user can increase up to the real available stock.
                // When the document is a draft OR the `create_receipt` flag is set
                // behave like create: do NOT add original_sale_quantity.
                $effectiveAvailable = $currentStock;
                if ($this->data && !$this->shouldTreatAsCreate() && isset($this->original_sale_quantity[$product_id])) {
                    $effectiveAvailable += (int) $this->original_sale_quantity[$product_id];
                }

                $attemptedQty = isset($this->quantity[$product_id]) ? (int) $this->quantity[$product_id] : (int) $item->qty;

                $debugSnapshot[] = [
                    'rowId' => $item->rowId,
                    'product_id' => $product_id,
                    'qty' => (int) $item->qty,
                    'attemptedQty' => $attemptedQty,
                    'stock_option' => (int) ($item->options->stock ?? 0),
                    'check_quantity_map' => isset($this->check_quantity[$product_id]) ? (int) $this->check_quantity[$product_id] : null,
                    'effectiveAvailable' => $effectiveAvailable,
                ];

                    if ($attemptedQty > $effectiveAvailable) {
                        // Only mark rows as invalid for modules that reduce stock
                        // when saved (sale and purchase_return). For modules that
                        // only add products (sale_return, purchase) do not
                        // highlight rows even if attemptedQty > available.
                        if (in_array($this->cart_instance, ['sale', 'sale_edit', 'purchase_return'])) {
                            $invalidIds[] = (int) $product_id;
                        }
                    }
            }
        } catch (\Exception $e) {
            // On failure assume valid to avoid blocking form submits unexpectedly
            $invalidIds = [];
        }

        $invalid = !empty($invalidIds);

        // Write debug snapshot to log when running in debug mode (only when something is invalid)
        if (config('app.debug') && !empty($debugSnapshot)) {
            try { Log::info('updateValidity snapshot', ['cart_instance' => $this->cart_instance, 'snapshot' => $debugSnapshot, 'invalid' => $invalid]); } catch (\Exception $e) { /* ignore */ }
        }

        $this->has_invalid_quantity = $invalid;
        $this->invalid_row_ids = $invalidIds;
        if ($invalid) {
            // Only show the textual validation message for cart instances that
            // actually reduce stock when saved (sale and purchase_return).
            if (in_array($this->cart_instance, ['sale', 'sale_edit', 'purchase_return'])) {
                if ($this->cart_instance === 'purchase_return') {
                    $this->validation_message = 'The requested quantity is not available in the purchased stock quantity. The quantity will not be reduced from the open stock quantity.';
                } else {
                    $this->validation_message = 'The requested quantity is not available in stock for one or more items.';
                }
            } else {
                // For modules that don't reduce stock (e.g. sale_return, purchase)
                // suppress the human-visible validation message while still
                // reporting validity state so the UI can decide what to do.
                $this->validation_message = '';
            }
        } else {
            $this->validation_message = '';
        }

        $payload = ['valid' => !$invalid, 'invalid_ids' => $invalidIds, 'message' => $this->validation_message];
        // Dispatch via Livewire v3 (dispatch), v2 emit, and browser event — widest compatibility
        try { $this->dispatch('cart-validity', $payload); } catch (\Exception $e) { /* ignore */ }
        try { if (method_exists($this, 'emit')) { $this->emit('cart-validity', $payload); } } catch (\Exception $e) { /* ignore */ }
        try { if (method_exists($this, 'dispatchBrowserEvent')) { $this->dispatchBrowserEvent('cart-validity', $payload); } } catch (\Exception $e) { /* ignore */ }
    }

    public function updateCartOptions($row_id, $product_id = null, $cart_item = null, $discount_amount = 0) {
        if ($cart_item) {
            // Update options directly on the cart item object
            $updated_options = $this->getUpdatedCartOptions($cart_item, $row_id, $product_id, $discount_amount);
            foreach ($updated_options as $key => $value) {
                $cart_item->options->put($key, $value);
            }
            $this->invalidateCartCache();
        } else {
            // Fallback to Cart::update if no cart_item provided
            $cart = $this->cart();
            if (!$cart->content()->has($row_id)) {
                // Item no longer exists, skip update
                return;
            }
            $cart_item = $cart->get($row_id);
            $cart->update($row_id, [
                'options' => $this->getUpdatedCartOptions($cart_item, $row_id, $product_id, $discount_amount)
            ]);
            $this->invalidateCartCache();
        }
    }

    public function updatedGlobalTaxAmount() {
        // Overall calculations are recomputed reactively by getOverallCalculationsProperty().
    }

    public function updatedGlobalDiscountAmount() {
        // Overall calculations are recomputed reactively by getOverallCalculationsProperty().
    }

    public function updatedAdjustment($value)
    {
        // Validate adjustment value
        if (!is_numeric($value) && $value !== '' && $value !== null) {
            $this->adjustment = 0;
            return;
        }

        // Convert to float and limit decimal places
        $this->adjustment = $value === '' || $value === null ? 0 : round((float)$value, 2);
    }

    public function updateQuantity($row_id, $product_id) {
        if ($this->readonly) return;
        if ($this->cart_instance == 'sale' || $this->cart_instance == 'purchase_return') {
            $currentStock = isset($this->check_quantity[$product_id]) ? (int) $this->check_quantity[$product_id] : 0;
            $desiredQty   = isset($this->quantity[$product_id]) ? (int) $this->quantity[$product_id] : 0;

            // When editing an existing sale ($this->data is set), the DB stock
            // has already been reduced by the original sale quantity.
            // Add that fixed original qty back so the user can increase within
            // the real available stock.  For new sales only the raw DB stock applies.
            // For drafts or when `create_receipt` is set behave like a create page
            // and do NOT add original qty back.
            $effectiveAvailable = $currentStock;
            if ($this->data && !$this->shouldTreatAsCreate() && isset($this->original_sale_quantity[$product_id])) {
                $effectiveAvailable = $currentStock + (int) $this->original_sale_quantity[$product_id];
            }

            if ($desiredQty > $effectiveAvailable) {
                // Only flash a user-facing error for modules that will reduce
                // stock when saved (sale and purchase_return). For modules that
                // don't reduce stock (sale_return, purchase) suppress the flash.
                if (in_array($this->cart_instance, ['sale', 'sale_edit', 'purchase_return'])) {
                    if ($this->cart_instance === 'purchase_return') {
                        session()->flash('message', 'The requested quantity is not available in the purchased stock quantity. The quantity will not be reduced from the open stock quantity.');
                    } else {
                        session()->flash('message', 'The requested quantity is not available in stock for one or more items.');
                    }
                }
                // Debug: log rejected update attempt
                if (config('app.debug')) {
                    try {
                        Log::info('updateQuantity rejected', ['cart_instance' => $this->cart_instance, 'product_id' => $product_id, 'desiredQty' => $desiredQty, 'effectiveAvailable' => $effectiveAvailable]);
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
                // Re-scan ALL cart items so every invalid row (not just this one) stays highlighted.
                // $this->quantity[$product_id] already holds the rejected value (set by Livewire wire:model),
                // so updateValidity() will correctly include this product AND any others already invalid.
                $this->updateValidity();
                return;
            }
        }

        $cart = $this->cart();
        $actual_row_id = $this->findValidRowId($cart, $row_id, $product_id);
        
        if (!$actual_row_id) {
            // Item not in cart, skip update silently
            return;
        }

        // Use safe update method to prevent item removal on zero quantity
        $this->safeUpdateQuantity($cart, $actual_row_id, $this->quantity[$product_id]);
        // Invalidate cache so updateItemPrice reads the new qty
        $this->invalidateCartCache();
        // Full recalculation so sub_total, tax_amount, amount options are all in sync
        $this->updateItemPrice($actual_row_id);
        // Recompute global discount total (per-unit discounts scale by qty)
        $this->global_discount_amount = $this->computeGlobalEffectiveDiscount();
        // Debug: log cart item after quantity update
        if (config('app.debug')) {
            try {
                $cart_item_after = $this->cart()->get($actual_row_id);
                Log::info('updateQuantity applied', ['cart_instance' => $this->cart_instance, 'rowId' => $actual_row_id, 'product_id' => $product_id, 'qty_after' => $cart_item_after ? $cart_item_after->qty : null]);
            } catch (\Exception $e) {
                // ignore
            }
        }
        // Re-evaluate cart validity after quantity changes so UI highlights remain accurate
        $this->updateValidity();
    }

    /**
     * Livewire lifecycle hook fired when $this->quantity[$productId] is synced
     * via wire:model.lazy. At this point the new value is already in
     * $this->quantity[$productId], so we can update the cart session
     * directly — eliminating the blur-before-change race condition that
     * existed when wire:blur was used instead.
     */
    public function updatedQuantity($value, $productId)
    {
        if ($this->readonly) return;

        // Use cached cart items (consistent with rest of codebase; avoids raw session read)
        $item = $this->getCachedCartItems()->firstWhere('id', $productId);
        if (!$item) return;

        $this->updateQuantity($item->rowId, $productId);
    }

    public function updatedDiscountType($value, $name) {
        $this->item_discount[$name] = 0;
    }

    public function discountModalRefresh($product_id, $row_id) {
        $this->updateQuantity($row_id, $product_id);
    }

    public function setProductDiscount($row_id, $product_id) {
        if ($this->readonly) return;
        $cart_item = $this->cart()->get($row_id);
        if (!$cart_item) return; // Prevent error if rowId not found
        // Normalize user-provided discount input: empty strings or non-numeric values -> 0
        $raw = $this->item_discount[$product_id] ?? 0;
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            $discountValue = 0.0;
        } else {
            $discountValue = (float) $raw;
        }

        $discountType = $this->discount_type[$product_id] ?? 'percentage'; // Default to percentage for Dis % column
        
        // Store the discount values but don't update price directly
        // Let updateItemPrice handle the final price calculation
        $this->item_discount[$product_id] = $discountValue;
        $this->discount_type[$product_id] = $discountType;
        
        // Trigger price recalculation
        $this->updateItemPrice($row_id);

        session()->flash('discount_message' . $product_id, 'Discount added to the product!');
    }

    public function updatePrice($row_id, $product_id) {
        if ($this->readonly) return;
        $product = Product::findOrFail($product_id);
        $this->cart()->update($row_id, ['price' => $this->unit_price[$product['id']]]);
        $this->invalidateCartCache();
        $this->updateCartOptions($row_id);
    }

    public function getCartItemsProperty()
    {
        // Reverse so the most-recently added product appears at the top of the table.
        // Only affects display order — all internal logic still uses getCachedCartItems() directly.
        return $this->getCachedCartItems()->reverse();
    }

    public function getOverallCalculationsProperty()
    {
        $cart_items = $this->getCachedCartItems();

        $item_count = $cart_items->count();
        $overall_nos = $item_count;
        $overall_quantity        = 0;
        $overall_total_without_gst = 0.0; // sum of "Total (w/o GST)" column
        $overall_tax_amount      = 0.0;   // sum of "Tax Amount" (GST) column
        $overall_amount          = 0.0;   // sum of "Amount incl. GST" column
        $overall_mrp_amount      = 0.0;   // sum of MRP × qty (used as Gross for sale-like modules)

        // Mirror the blade @php block exactly so totals always match the per-row display.
        foreach ($cart_items as $item) {
            $id  = $item->id;
            $qty = (float) $item->qty;
            $overall_quantity += $qty;

            // Tax % (col 7) — drives Rate before Discount = MRP / (1 + tax%)
            $_tax_pct = (float) ($this->tax_percent_edit[$id] ?? $item->options->tax_percent ?? 0);
            // GST % — drives Tax Amount and Amount incl. GST (independent)
            $_gst_pct = (float) ($this->gst_percent[$id] ?? $item->options->gst_percent ?? $_tax_pct);

            $_mrp_val = (float) ($this->mrp[$id] ?? $item->options->mrp ?? 0);

            // Authoritative pre-tax rate (same priority as blade)
            // If the component has an explicit rate entry (even if zero), treat
            // that as authoritative. Else, if the cart options store a
            // rate_before_discount (even if zero) use it. Otherwise derive from MRP.
            if (is_array($this->rate) && array_key_exists($id, $this->rate)) {
                $_rate_before_discount_precise = (float) $this->rate[$id];
            } elseif (isset($item->options->rate_before_discount)) {
                $_rate_before_discount_precise = (float) $item->options->rate_before_discount;
            } else {
                $_rate_before_discount_precise = (($_tax_pct > 0) ? ($_mrp_val / (1 + $_tax_pct / 100)) : $_mrp_val);
            }

            // Discounts removed from all transaction modules — net rate is the rate as entered.
            // Round to 2 dp FIRST (mirrors blade) so per-row and overall totals match.
            $_net_rate = round(max(0.0, $_rate_before_discount_precise), 2);

            // Per-row totals (same rounding as blade)
            $_total_without_gst  = round($_net_rate * $qty, 2);
            $_tax_amount_display = round($_total_without_gst * $_gst_pct / 100, 2);

            $overall_total_without_gst += $_total_without_gst;
            $overall_tax_amount        += $_tax_amount_display;
            // amount including GST per-row (always computed as pre-tax total + tax)
            $overall_amount            += ($_total_without_gst + $_tax_amount_display);
            // sum of mrp × qty for gross display in sale flows
            $overall_mrp_amount       += ($_mrp_val * $qty);
        }

        // Final rounding of sums
        $overall_total_without_gst = round($overall_total_without_gst, 2);
        $overall_tax_amount        = round($overall_tax_amount, 2);
        $overall_amount            = round($overall_amount, 2);

        // For sale-like modules show Gross as sum(MRP × qty) and make Amount equal to taxable total
        if (in_array($this->cart_instance, ['sale', 'sale_edit', 'sale_return', 'quotation', 'quotation_edit', 'sale_view', 'sale_return_view', 'quotation_view'])) {
            $overall_gross_amount   = round($overall_mrp_amount, 2); // sum of MRPs
            $overall_taxable_amount = $overall_total_without_gst;   // pre-tax total
            // Grand Total for sale flows = taxable + VAT
            $overall_amount = round($overall_total_without_gst + $overall_tax_amount, 2);
        } else {
            // Type 4 purchase/purchase_return mode: use pre-tax total (GST column hidden)
            $isPurchaseType4 = $this->isPurchaseCartInstance(true)
                && $this->purchase_type == 4;
            if ($isPurchaseType4) {
                $overall_amount       = $overall_total_without_gst;
                $overall_gross_amount = $overall_total_without_gst;
            } else {
                $overall_gross_amount = $overall_amount; // total incl. GST
            }
            $overall_taxable_amount = $overall_total_without_gst;
        }

        return [
            'overall_nos'           => $overall_nos,
            'overall_quantity'      => $overall_quantity,
            'overall_gross_amount'  => (float)$overall_gross_amount,
            'overall_taxable_amount'=> (float)max(0, $overall_taxable_amount),
            'overall_tax_amount'    => (float)$overall_tax_amount,
            'overall_amount'        => (float)$overall_amount,
        ];
    }

    public function applyCustomerAdditionalDiscount($data) {
        if ($this->readonly) return;
        $additionalDiscountPercent = $data['discount'];
        $cart_items = $this->getCachedCartItems();

        // Batch-apply the additional percent to internal state first to avoid
        // repeated session writes/flash messages from `setProductDiscount`.
        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item->id;
            // Store as percentage discount type and value
            $this->discount_type[$product_id] = 'percentage';
            $this->item_discount[$product_id] = (float) $additionalDiscountPercent;
        }

        // Now recalculate prices for all items (single session updates per item)
        foreach ($cart_items as $cart_item) {
            $this->updateItemPrice($cart_item->rowId);
        }

        // Invalidate cache and refresh UI once
        $this->invalidateCartCache();
        $this->dispatch('$refresh');
    }

    /**
     * Apply customer cash discount percent to all cart items and recalc prices.
     * Accepts either a numeric percent or an array with ['percent' => x] to be consistent.
     */
    public function applyCustomerCashDiscount($payload)
    {
        if ($this->readonly) return;
        $percent = 0;
        if (is_array($payload)) {
            $percent = $payload['percent'] ?? ($payload['discount'] ?? 0);
        } else {
            $percent = $payload;
        }

        $percent = is_numeric($percent) ? (float)$percent : 0.0;

        $cart_items = $this->getCachedCartItems();
        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item->id;
            // update stored cash discount percent for this product
            $this->cash_discount_percent[$product_id] = intval($percent);
            // ensure any explicit cash discount amount is cleared when percent is applied
            $this->cash_discount_amount[$product_id] = 0;
            // recalc item price
            $this->updateItemPrice($cart_item->rowId);
        }

        // recompute global summary values
        $this->global_discount_amount = $this->computeGlobalEffectiveDiscount();
        $this->invalidateCartCache();
        $this->dispatch('$refresh');
    }

    public function setPurchaseType($type)
    {
        $this->withUpdateLock(function() use ($type) {
            $this->purchase_type = (int) $type;

            // Only apply changes for purchase-related cart instances
            if (!$this->isPurchaseCartInstance()) {
                return;
            }
            // When purchase type is 4, force Rate before Discount to product_cost for all cart items.
            // Otherwise restore Rate Type to 'M' (MRP-derived) and recompute rates from MRP/tax.
            $cart_items = $this->getCachedCartItems();
            $ids = $cart_items->pluck('id')->unique()->values()->all();
            if (!empty($ids)) {
                $products = Product::whereIn('id', $ids)->get()->keyBy('id');
            } else {
                $products = collect();
            }

            $cart = $this->cart();
            if ($this->purchase_type === 4) {
                foreach ($cart_items as $cart_item) {
                    $product_id = $cart_item->id;
                    $product = $products->get($product_id);
                    if (!$product) continue;

                    // Prefer product_cost; if missing, leave existing rate unchanged
                    if (!is_null($product->product_cost)) {
                        $purchaseRate = (float) $product->product_cost;

                        // Update Livewire state
                        $this->rate[$product_id] = $purchaseRate;
                        $this->rate_type[$product_id] = 'N';

                        // Persist to cart options: set rate_before_discount and rate_type
                        $opts = $cart_item->options->toArray();
                        $opts['rate_before_discount'] = $purchaseRate;
                        $opts['rate_type'] = 'N';
                        $cart->update($cart_item->rowId, ['options' => $opts]);

                        // Recalculate item price using the new pre-tax purchase rate
                        $this->updateItemPrice($cart_item->rowId);
                    }
                }
            } else {
                // Restore MRP-based rates for non-type-4 purchases
                foreach ($cart_items as $cart_item) {
                    $product_id = $cart_item->id;
                    $product = $products->get($product_id);
                    // Determine tax percent and MRP from options or product
                    $taxPercent = floatval($cart_item->options->tax_percent ?? ($product->product_order_tax ?? 0));
                    $mrp = floatval($cart_item->options->mrp ?? ($product->mrp ?? $cart_item->price));

                    // Type-3 mirrors MRP directly in Rate Before Discount.
                    // Other types keep pre-tax derivation from MRP.
                    $derivedRate = $this->purchase_type === 3
                        ? $mrp
                        : ($taxPercent > 0 ? ($mrp / (1 + ($taxPercent / 100))) : $mrp);

                    // Update Livewire state to 'M' (MRP) and set rate to derived
                    $this->rate_type[$product_id] = 'M';
                    $this->rate[$product_id] = round((float)$derivedRate, 2);

                    // Persist to cart options
                    try {
                        $opts = $cart_item->options->toArray();
                        $opts['rate_before_discount'] = (float)$derivedRate;
                        $opts['rate_type'] = 'M';
                        $cart->update($cart_item->rowId, ['options' => $opts]);
                    } catch (\Exception $e) {
                        // ignore persistence errors
                    }

                    // Recalculate item price using the new rate
                    $this->updateItemPrice($cart_item->rowId);
                }
            }

            $this->invalidateCartCache();
            $this->dispatch('refreshCart');
        });
    }
}