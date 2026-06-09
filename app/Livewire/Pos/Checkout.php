<?php

namespace App\Livewire\Pos;

use Gloudemans\Shoppingcart\Facades\Cart;
use Livewire\Component;

class Checkout extends Component
{

    public $listeners = ['productSelected', 'discountModalRefresh'];

    public $cart_instance;
    public $customers;
    public $global_discount;
    public $global_discount_amount;
    public $global_tax;
    public $global_tax_amount;
    public $shipping;
    public $quantity;
    public $check_quantity;
    public $discount_type;
    public $item_discount;
    public $data;
    public $customer_id;
    public $total_amount;

    public function mount($cartInstance, $customers) {
        $this->cart_instance = $cartInstance;
        $this->customers = $customers;
        $this->global_discount = 0;
        $this->global_discount_amount = 0;
        $this->global_tax = 0;
        $this->global_tax_amount = 0;
        $this->shipping = 0.00;
        $this->check_quantity = [];
        $this->quantity = [];
        $this->discount_type = [];
        $this->item_discount = [];
        $this->total_amount = $this->calculateTotal(); // Ensure total_amount is set for the initial render
    }

    public function hydrate() {
        // Always ensure shipping, tax, and discount are valid numbers
        if (empty($this->shipping) || !is_numeric($this->shipping)) {
            $this->shipping = 0;
        }
        if (empty($this->global_tax) || !is_numeric($this->global_tax)) {
            $this->global_tax = 0;
        }
        if (empty($this->global_tax_amount) || !is_numeric($this->global_tax_amount)) {
            $this->global_tax_amount = 0;
        }
        if (empty($this->global_discount) || !is_numeric($this->global_discount)) {
            $this->global_discount = 0;
        }
        if (empty($this->global_discount_amount) || !is_numeric($this->global_discount_amount)) {
            $this->global_discount_amount = 0;
        }
        $this->total_amount = $this->calculateTotal();
    }

    public function render() {
        $cart_items = Cart::instance($this->cart_instance)->content();

        return view('livewire.pos.checkout', [
            'cart_items' => $cart_items
        ]);
    }

    public function proceed() {
        // Ensure shipping, tax, and discount are always numbers and not empty
        if (empty($this->shipping) || !is_numeric($this->shipping)) {
            $this->shipping = 0;
        }
        if (empty($this->global_tax) || !is_numeric($this->global_tax)) {
            $this->global_tax = 0;
        }
        if (empty($this->global_tax_amount) || !is_numeric($this->global_tax_amount)) {
            $this->global_tax_amount = 0;
        }
        if (empty($this->global_discount) || !is_numeric($this->global_discount)) {
            $this->global_discount = 0;
        }
        if (empty($this->global_discount_amount) || !is_numeric($this->global_discount_amount)) {
            $this->global_discount_amount = 0;
        }
        // Also update total_amount to reflect the change
        $this->total_amount = $this->calculateTotal();
        if ($this->customer_id != null) {
            $this->dispatch('showCheckoutModal');
        } else {
            session()->flash('message', 'Please Select Customer!');
        }
    }

    public function calculateTotal() {
        $cartTotal = Cart::instance($this->cart_instance)->total();
        $taxAmount = (float) $this->global_tax_amount;
        $discountAmount = (float) $this->global_discount_amount;
        $shipping = (float) $this->shipping;

        return $cartTotal + $taxAmount - $discountAmount + $shipping;
    }

    public function resetCart() {
        Cart::instance($this->cart_instance)->destroy();
    }

    public function productSelected($product) {
        $cart = Cart::instance($this->cart_instance);

        $exists = $cart->search(function ($cartItem, $rowId) use ($product) {
            return $cartItem->id == $product['id'];
        });

        if ($exists->isNotEmpty()) {
            session()->flash('message', 'Product exists in the cart!');

            return;
        }

        $cart->add([
            'id'      => $product['id'],
            'name'    => $product['product_name'],
            'qty'     => 1,
            'price'   => $this->calculate($product)['price'],
            'weight'  => 1,
            'options' => [
                'product_discount'      => 0.00,
                'product_discount_type' => 'fixed',
                'sub_total'             => $this->calculate($product)['sub_total'],
                'code'                  => $product['product_code'],
                'stock'                 => $product['product_quantity'],
                'unit'                  => $product['product_unit'],
                'product_tax'           => $this->calculate($product)['product_tax'],
                'unit_price'            => $this->calculate($product)['unit_price']
            ]
        ]);

        $this->check_quantity[$product['id']] = $product['product_quantity'];
        $this->quantity[$product['id']] = 1;
        $this->discount_type[$product['id']] = 'fixed';
        $this->item_discount[$product['id']] = 0;
        $this->total_amount = $this->calculateTotal();
    }

    public function removeItem($row_id) {
        Cart::instance($this->cart_instance)->remove($row_id);
    }

    public function updatedGlobalTax() {
        Cart::instance($this->cart_instance)->setGlobalTax((integer)$this->global_tax);
    }

    public function updatedGlobalDiscount() {
        Cart::instance($this->cart_instance)->setGlobalDiscount((integer)$this->global_discount);
    }

    public function updateQuantity($row_id, $product_id) {
        if ($this->check_quantity[$product_id] < $this->quantity[$product_id]) {
            session()->flash('message', 'The requested quantity is not available in stock.');

            return;
        }

        // Sanitize and validate quantity input
        $quantity = preg_replace('/[^0-9]/', '', $this->quantity[$product_id]); // Remove non-numeric characters
        $quantity = (int) $quantity; // Convert to integer
        
        // Prevent removal of item when quantity is set to 0 or empty
        if ($quantity <= 0) {
            return;
        }

        // Find valid rowId with fallback
        $cart = Cart::instance($this->cart_instance);
        $actual_row_id = $this->findValidRowId($cart, $row_id, $product_id);
        
        if (!$actual_row_id) {
            return; // Item not found
        }

        $cart->update($actual_row_id, $quantity);

        $cart_item = $cart->get($actual_row_id);

        $cart->update($actual_row_id, [
            'options' => [
                'sub_total'             => $cart_item->price * $cart_item->qty,
                'code'                  => $cart_item->options->code,
                'stock'                 => $cart_item->options->stock,
                'unit'                  => $cart_item->options->unit,
                'product_tax'           => $cart_item->options->product_tax,
                'unit_price'            => $cart_item->options->unit_price,
                'product_discount'      => $cart_item->options->product_discount,
                'product_discount_type' => $cart_item->options->product_discount_type,
            ]
        ]);
    }

    /**
     * Livewire lifecycle hook fired when $this->quantity[$productId] is synced
     * via wire:model.lazy. Replaces the old wire:blur="updateQuantity(...)" approach
     * to avoid blur-before-change race conditions.
     */
    public function updatedQuantity($value, $productId)
    {
        $cart = Cart::instance($this->cart_instance);
        $item = $cart->content()->firstWhere('id', $productId);
        if (!$item) return;

        $this->updateQuantity($item->rowId, $productId);
    }

    public function updatedDiscountType($value, $name) {
        $this->item_discount[$name] = 0;
    }

    public function updatedCustomerId($value) {
        if ($value) {
            $customer = \Modules\People\Entities\Customer::find($value);
            if ($customer && $customer->additional_discount > 0) {
                // Apply additional discount to each product in the cart
                $this->applyCustomerAdditionalDiscount($customer->additional_discount);
            }
        }
    }

    public function applyCustomerAdditionalDiscount($additionalDiscountPercent) {
        $cart_items = Cart::instance($this->cart_instance)->content();

        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item->id;
            $row_id = $cart_item->rowId;

            // Set discount type to percentage
            $this->discount_type[$product_id] = 'percentage';

            // Set the item discount to the customer's additional discount percentage
            $this->item_discount[$product_id] = $additionalDiscountPercent;

            // Apply the discount
            $this->setProductDiscount($row_id, $product_id);
        }
    }

    public function setProductDiscount($row_id, $product_id) {
        $cart_item = Cart::instance($this->cart_instance)->get($row_id);

        if ($this->discount_type[$product_id] == 'fixed') {
            Cart::instance($this->cart_instance)
                ->update($row_id, [
                    'price' => ($cart_item->price + $cart_item->options->product_discount) - $this->item_discount[$product_id]
                ]);

            $discount_amount = $this->item_discount[$product_id];

            $this->updateCartOptions($row_id, $product_id, $cart_item, $discount_amount);
        } elseif ($this->discount_type[$product_id] == 'percentage') {
            $discount_amount = ($cart_item->price + $cart_item->options->product_discount) * ($this->item_discount[$product_id] / 100);

            Cart::instance($this->cart_instance)
                ->update($row_id, [
                    'price' => ($cart_item->price + $cart_item->options->product_discount) - $discount_amount
                ]);

            $this->updateCartOptions($row_id, $product_id, $cart_item, $discount_amount);
        }

        session()->flash('discount_message' . $product_id, 'Discount added to the product!');
    }

    public function calculate($product) {
        $price = 0;
        $unit_price = 0;
        $product_tax = 0;
        $sub_total = 0;

        if ($product['product_tax_type'] == 1) {
            $price = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 100));
            $unit_price = $product['product_price'];
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 100);
            $sub_total = $product['product_price'] + ($product['product_price'] * ($product['product_order_tax'] / 100));
        } elseif ($product['product_tax_type'] == 2) {
            $price = $product['product_price'];
            $unit_price = $product['product_price'] - ($product['product_price'] * ($product['product_order_tax'] / 100));
            $product_tax = $product['product_price'] * ($product['product_order_tax'] / 100);
            $sub_total = $product['product_price'];
        } else {
            $price = $product['product_price'];
            $unit_price = $product['product_price'];
            $product_tax = 0.00;
            $sub_total = $product['product_price'];
        }

        return ['price' => $price, 'unit_price' => $unit_price, 'product_tax' => $product_tax, 'sub_total' => $sub_total];
    }

    /**
     * Find valid rowId for cart item, with fallback to product_id lookup
     */
    private function findValidRowId($cart, $row_id, $product_id)
    {
        // First try the provided rowId
        if ($cart->content()->has($row_id)) {
            return $row_id;
        }

        // Fallback: find by product_id
        $content = $cart->content();
        $item = $content->firstWhere('id', $product_id);
        
        return $item ? $item->rowId : null;
    }

    public function updateCartOptions($row_id, $product_id, $cart_item, $discount_amount) {
        Cart::instance($this->cart_instance)->update($row_id, ['options' => [
            'sub_total'             => $cart_item->price * $cart_item->qty,
            'code'                  => $cart_item->options->code,
            'stock'                 => $cart_item->options->stock,
            'unit'                 => $cart_item->options->unit,
            'product_tax'           => $cart_item->options->product_tax,
            'unit_price'            => $cart_item->options->unit_price,
            'product_discount'      => $discount_amount,
            'product_discount_type' => $this->discount_type[$product_id],
        ]]);
    }
}
