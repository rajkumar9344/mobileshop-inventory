<?php

namespace Modules\Sale\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HandlesMinorCurrency;
use Modules\Product\Entities\Product;

class SaleDetails extends Model
{
    use HasFactory;
    use HandlesMinorCurrency;

    /**
     * Explicit table name: use `sales_details` (plural) instead of `sale_details`.
     */
    protected $table = 'sales_details';

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'product_code',
        'product_code_id',
        'quantity',
        'price',
        'category',
        'mrp',
        'rate',
        'tax_percentage',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'unit_price',
        'sub_total',
        'product_discount_amount',
        'product_discount_type',
        'product_tax_amount'
    ];

    protected $with = ['product', 'productCode'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->rate) && !empty($model->mrp) && !empty($model->tax_percentage)) {
                $model->rate = $model->mrp / (1 + ($model->tax_percentage / 100));
            }
        });
    }

    public function product() {
        return $this->belongsTo(Product::class, 'product_id', 'id')->withDefault([
            'product_name' => '',
            'product_code' => '',
            'category' => null,
            'product_price' => 0
        ]);
    }

    public function productCode()
    {
        return $this->belongsTo(\Modules\Product\Entities\ProductCode::class, 'product_code_id', 'id')->withDefault([
            'code' => '',
            'is_primary' => false
        ]);
    }

    public function sale() {
        return $this->belongsTo(Sale::class, 'sale_id', 'id');
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

    public function getTaxAmountAttribute($value) {
        return $value / 100;
    }

    public function getDiscountAmountAttribute($value) {
        return $value / 100;
    }


    public function setPriceAttribute($value) { $this->attributes['price'] = $this->toMinor($value); }
    public function setUnitPriceAttribute($value) { $this->attributes['unit_price'] = $this->toMinor($value); }
    public function setSubTotalAttribute($value) { $this->attributes['sub_total'] = $this->toMinor($value); }
    public function setProductDiscountAmountAttribute($value) { $this->attributes['product_discount_amount'] = $this->toMinor($value); }
    public function setProductTaxAmountAttribute($value) { $this->attributes['product_tax_amount'] = $this->toMinor($value); }
    public function setMrpAttribute($value) { $this->attributes['mrp'] = $this->toMinor($value); }
    public function setRateAttribute($value) { $this->attributes['rate'] = $this->toMinor($value); }
    public function setTaxAmountAttribute($value) { $this->attributes['tax_amount'] = $this->toMinor($value); }
    public function setDiscountAmountAttribute($value) { $this->attributes['discount_amount'] = $this->toMinor($value); }
}
