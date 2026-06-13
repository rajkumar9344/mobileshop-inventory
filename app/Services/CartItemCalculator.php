<?php

namespace App\Services;

/**
 * Shared helper for computing per-line financial values from a cart item.
 *
 * Formula (matches display, India GST compliant):
 *   after_percent_discount = MRP × (1 − disc% / 100)
 *   cash_discount_total    = after_percent × cash_disc% / 100 + cash_disc_amount
 *   net_rate (pre-tax)     = after_percent_discount − cash_discount_total
 *   sub_total              = net_rate × qty
 *   tax_amount             = sub_total × tax% / 100
 *
 * This fixes the common mistake of deriving net_rate as MRP / (1 + tax%)
 * which produces a lower tax base than shown in the UI.
 */
class CartItemCalculator
{
    /**
     * Compute all per-line financial values for a single Gloudemans cart item.
     *
     * @param  mixed       $cartItem  Gloudemans cart item object
     * @param  mixed|null  $product   Eloquent Product model (used as tax_percent fallback)
     * @return array{
     *   mrp: float,
     *   rate: float,
     *   tax_percent: float,
     *   tax_amount: float,
     *   unit_price: float,
     *   sub_total: float,
     *   discount_amount: float,
     *   cash_discount_percent: float,
     *   cash_discount_amount: float,
     * }
     */
    /**
     * Single source of truth for a BRD cart line (no discounts).
     *
     *   amount        = rate × qty              (pre-VAT)
     *   vat_amount    = amount × vat%           (2 dp)
     *   final_amount  = amount + vat_amount     (incl. VAT, 2 dp)
     *   price_with_vat= rate × (1 + vat%)       (per-unit incl. VAT)
     *
     * Used by the Blade row, ProductCart::updateItemPrice(), and the overall
     * totals so the per-row figures and the overall figures can never diverge.
     *
     * @return array{rate: float, qty: float, vat_pct: float, amount: float,
     *               vat_amount: float, final_amount: float, price_with_vat: float,
     *               tax_per_unit: float}
     */
    public static function lineTotals(float $ratePreTax, float $qty, float $vatPct): array
    {
        $rate         = max(0.0, $ratePreTax);
        $amount       = round($rate * $qty, 2);
        $vatAmount    = round($amount * $vatPct / 100, 2);
        $finalAmount  = round($amount + $vatAmount, 2);
        $priceWithVat = $rate * (1 + ($vatPct / 100));

        return [
            'rate'           => $rate,
            'qty'            => (float) $qty,
            'vat_pct'        => (float) $vatPct,
            'amount'         => $amount,
            'vat_amount'     => $vatAmount,
            'final_amount'   => $finalAmount,
            'price_with_vat' => $priceWithVat,
            'tax_per_unit'   => round($priceWithVat - $rate, 2),
        ];
    }

    public static function compute($cartItem, $product = null): array
    {
        $options = $cartItem->options;

        $mrp                 = (float) ($options->mrp ?? $cartItem->price ?? 0);
        $taxPercent          = (float) ($options->tax_percent ?? ($product?->product_order_tax ?? 0));
        $cashDiscountPercent = (float) ($options->cash_discount_percent ?? 0);
        $cashDiscountAmount  = (float) ($options->cash_discount_amount  ?? 0);
        $discountPercent     = (float) ($options->product_discount_percent ?? 0);

        // Net pre-tax rate (= "Net Rate" column in the UI)
        $afterPct   = $mrp * (1 - $discountPercent / 100);
        $cashTotal  = $afterPct * ($cashDiscountPercent / 100) + $cashDiscountAmount;
        $rate       = round(max(0.0, $afterPct - $cashTotal), 2);

        $subTotal       = round($rate * $cartItem->qty, 2);
        $taxAmount      = round($subTotal * $taxPercent / 100, 2);
        $discountAmount = (float) ($options->product_discount ?? round(($mrp - $rate) * $cartItem->qty, 2));

        return [
            'mrp'                  => $mrp,
            'rate'                 => $rate,
            'tax_percent'          => $taxPercent,
            'tax_amount'           => $taxAmount,
            'unit_price'           => $rate,
            'sub_total'            => $subTotal,
            'discount_amount'      => $discountAmount,
            'cash_discount_percent' => $cashDiscountPercent,
            'cash_discount_amount'  => $cashDiscountAmount,
        ];
    }
}
