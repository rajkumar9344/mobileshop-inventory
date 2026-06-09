<div class="input-group d-flex justify-content-center">
    <input 
        data-quick-qty="1"
        wire:model.lazy="quantity.{{ $cart_item->id }}"
        style="min-width: 28px; max-width: 60px; padding:4px 6px; text-align:center;" 
        type="number" 
        class="form-control" 
        min="1" 
        max="99999"
        step="1"
        pattern="[0-9]*"
        inputmode="numeric"
        oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value < 1) this.value = '';"
        onkeypress="return event.charCode >= 48 && event.charCode <= 57"
        maxlength="5"
        {{ (isset($isReadOnly) && $isReadOnly) ? 'readonly' : '' }}>
</div>
