<?php

namespace Modules\SalesReturn\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Product\Entities\Product;
use App\Traits\HandlesMinorCurrency;

class SaleReturnDetail extends Model
{
    use HasFactory;
    use HandlesMinorCurrency;

    protected $guarded = [];

    protected $with = ['product', 'productCode'];

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
    public function productCode()
    {
        return $this->belongsTo(\Modules\Product\Entities\ProductCode::class, 'product_code_id', 'id')->withDefault([
            'code' => '',
            'is_primary' => false
        ]);
    }

    public function saleReturn() {
        return $this->belongsTo(SaleReturnPayment::class, 'sale_return_id', 'id');
    }

    public function getPriceAttribute($value) {
        return $value / 100;
    }

    public function getUnitPriceAttribute($value) {
        return $value / 100;
    }

    public function getSubTotalAttribute($value) {
        return $value / 100;
    }

    public function getProductDiscountAmountAttribute($value) {
        return $value / 100;
    }

    public function getProductTaxAmountAttribute($value) {
        return $value / 100;
    }

    public function getMrpAttribute($value) {
        return $value / 100;
    }

    public function getRateAttribute($value) {
        return $value / 100;
    }

    public function getCashDiscountAmountAttribute($value) {
        return $value / 100;
    }

    public function getTaxAmountAttribute($value) {
        return $value / 100;
    }

    public function getAmountAttribute($value) {
        return $value / 100;
    }

    // Mutators for monetary fields so assigning rupee values stores paise
    public function setMrpAttribute($value) { $this->attributes['mrp'] = $this->toMinor($value); }
    public function setRateAttribute($value) { $this->attributes['rate'] = $this->toMinor($value); }
    public function setProductTaxAmountAttribute($value) { $this->attributes['product_tax_amount'] = $this->toMinor($value); }
    public function setCashDiscountAmountAttribute($value) { $this->attributes['cash_discount_amount'] = $this->toMinor($value); }
    public function setProductDiscountAmountAttribute($value) { $this->attributes['product_discount_amount'] = $this->toMinor($value); }
    public function setPriceAttribute($value) { $this->attributes['price'] = $this->toMinor($value); }
    public function setUnitPriceAttribute($value) { $this->attributes['unit_price'] = $this->toMinor($value); }
    public function setSubTotalAttribute($value) { $this->attributes['sub_total'] = $this->toMinor($value); }
    public function setTaxAmountAttribute($value) { $this->attributes['tax_amount'] = $this->toMinor($value); }
    public function setAmountAttribute($value) { $this->attributes['amount'] = $this->toMinor($value); }
}
