<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Modules\Product\Notifications\NotifyQuantityAlert;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;


    protected $guarded = [];

    protected $with = ['media', 'category'];

    protected $appends = ['category_name'];

    public function getCategoryNameAttribute()
    {
        return $this->category ? $this->category->category_name : '-';
    }

    public function toArray()
    {
        $array = parent::toArray();
        $array['category'] = $this->category ? ['name' => $this->category->category_name] : ['name' => '-'];
        return $array;
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function productCodes()
    {
        return $this->hasMany(ProductCode::class, 'product_id', 'id');
    }

    public function registerMediaCollections(): void {
        $this->addMediaCollection('images')
            ->useFallbackUrl('/images/fallback_product_image.png');
    }

    public function registerMediaConversions(Media $media = null): void {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50);
    }
    /**
     * Convert a major-unit amount (e.g. rupees) to minor units (e.g. paise) for DB storage.
     */
    protected function toMinor($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) $value = str_replace(',', '', $value);

        // Allow numeric strings; use float for multiplication then round
        return (int) round(floatval($value) * 100);
    }

    /**
     * Convert a stored minor-unit integer to major units for presentation.
     */
    protected function fromMinor($value)
    {
        if ($value === null) {
            return null;
        }

        return $value / 100;
    }

    public function setProductCostAttribute($value) {
        $this->attributes['product_cost'] = $this->toMinor($value);
    }

    public function getProductCostAttribute($value) {
        return $this->fromMinor($value);
    }

    public function setProductPriceAttribute($value) {
        $this->attributes['product_price'] = $this->toMinor($value);
    }

    public function getProductPriceAttribute($value) {
        return $this->fromMinor($value);
    }

    public function setBuyPriceAttribute($value) {
        $this->attributes['buy_price'] = $this->toMinor($value);
    }

    public function getBuyPriceAttribute($value) {
        return $this->fromMinor($value);
    }

    public function setListPriceAttribute($value) {
        $this->attributes['list_price'] = $this->toMinor($value);
    }

    public function getListPriceAttribute($value) {
        return $this->fromMinor($value);
    }

    public function getReOrderAttribute() {
        $qty = (int) ($this->product_quantity ?? 0);
        $alert = (int) ($this->product_stock_alert ?? 0);

        if ($qty < $alert) {
            return $alert - $qty;
        }

        return 0;
    }

    public function recalculateProductQuantity() {
        $open = $this->open_quantity ?? 0;
        $purchase = $this->purchase_quantity ?? 0;

        if ($open < 0 || $purchase < 0) {
            Log::warning('Product quantities contained negative values; clamping to zero for calculation', [
                'product_id' => $this->id,
                'open_quantity' => $open,
                'purchase_quantity' => $purchase
            ]);
            $open = max(0, $open);
            $purchase = max(0, $purchase);
        }

        $this->update([
            'product_quantity' => max(0, $open + $purchase)
        ]);
    }

    /**
     * Return the currently available total stock (open + purchase).
     */
    public function availableQuantity()
    {
        return ($this->open_quantity ?? 0) + ($this->purchase_quantity ?? 0);
    }

    /**
     * Check whether the requested quantity can be reserved from this product.
     */
    public function canReserve(int $qty): bool
    {
        return $this->availableQuantity() >= $qty;
    }

    /**
     * Reserve stock by decrementing `open_quantity` first then `purchase_quantity`.
     * Assumes the caller has locked the row (lockForUpdate) if needed.
     */
    public function reserveStock(int $qty)
    {
        $open = $this->open_quantity ?? 0;
        $purchase = $this->purchase_quantity ?? 0;

        if ($open >= $qty) {
            $this->update(['open_quantity' => $open - $qty]);
        } else {
            $this->update([
                'open_quantity' => 0,
                'purchase_quantity' => max(0, $purchase - ($qty - $open))
            ]);
        }

        $this->recalculateProductQuantity();
    }

    /**
     * Restore previously reserved stock back into `open_quantity`.
     * Assumes the caller has locked the row if needed.
     */
    public function restoreStock(int $qty)
    {
        $this->update(['open_quantity' => ($this->open_quantity ?? 0) + $qty]);
        $this->recalculateProductQuantity();
    }

    /**
     * Increment purchase_quantity by given qty and recalculate totals.
     */
    public function addPurchaseStock(int $qty)
    {
        $current = $this->purchase_quantity ?? 0;
        if ($current < 0) {
            Log::warning('addPurchaseStock called but product has negative purchase_quantity; treating current as 0', [
                'product_id' => $this->id,
                'purchase_quantity' => $current
            ]);
            $current = 0;
        }

        $new = $current + $qty;
        $this->update(['purchase_quantity' => $new]);
        $this->recalculateProductQuantity();
    }

    /**
     * Decrement purchase_quantity by given qty (floor at 0) and recalculate totals.
     */
    public function removePurchaseStock(int $qty)
    {
        $new = max(0, ($this->purchase_quantity ?? 0) - $qty);
        $this->update(['purchase_quantity' => $new]);
        $this->recalculateProductQuantity();
    }

    public function supplier()
    {
        return $this->belongsTo(\Modules\People\Entities\Supplier::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(\Modules\Sale\Entities\SaleDetails::class);
    }

    public function purchaseDetails()
    {
        return $this->hasMany(\Modules\Purchase\Entities\PurchaseDetail::class);
    }

    public function saleReturnDetails()
    {
        return $this->hasMany(\Modules\SalesReturn\Entities\SaleReturnDetail::class);
    }

    public function purchaseReturnDetails()
    {
        return $this->hasMany(\Modules\PurchasesReturn\Entities\PurchaseReturnDetail::class);
    }

    public function quotationDetails()
    {
        return $this->hasMany(\Modules\Quotation\Entities\QuotationDetails::class);
    }
}
