@props([
    'id',
    'name' => null,
    'hiddenName' => null,
    'class' => 'form-control',
    'maxlength' => 50,
    'ariaLabel' => 'currency input',
    'wireModel' => null,
    // Default to null so component emits plain `wire:model="..."` (immediate) unless caller
    // explicitly requests a modifier like 'lazy' or 'defer'. This avoids relying on programmatic
    // change events to trigger lazy updates and ensures the Livewire prop is updated when
    // the hidden input value changes.
    'wireModifier' => null,
    'display' => null,
    'value' => null,
    'hiddenId' => null,
    'symbol' => null,
    'position' => 'prefix',
])

@php
    $id = $id ?? ('ci_'.uniqid());
    $hidden = $hiddenId ?? ($id . '_raw');
    $nameAttr = $name ? 'name="'.e($name).'"' : '';
    $hiddenNameAttr = $hiddenName ? 'name="'.e($hiddenName).'"' : '';
    $displayVal = $display ?? $value ?? '';
    // Calculate raw value and hidden field value from display
    $hiddenVal = '';
    $rawVal = '';
    if ($displayVal !== '' && $displayVal !== null) {
        $numVal = floatval(str_replace(',', '', $displayVal));
        // Use up to 2 decimals, but suppress trailing .00
        $hiddenVal = (floor($numVal) == $numVal)
            ? number_format($numVal, 0, '.', '')
            : rtrim(rtrim(number_format($numVal, 2, '.', ''), '0'), '.');
        $rawVal = strval(round($numVal * 100));
    }
    // Convert `disabled` to `readonly` so the field stays focusable;
    // the JS focus handler shows the full value and blur won't trigger Livewire
    // when nothing changed.
    // NOTE: `:disabled="false"` still puts the key in the bag with value false/0/""
    // so we must check the truthy value, not just presence.
    $disabledVal = $attributes->get('disabled');
    $hasDisabled = $disabledVal === true || $disabledVal === 'true' || $disabledVal === '1' || $disabledVal === 1;
@endphp
<div>
    @if($symbol)
    <div class="input-group">
        @if($position == 'prefix')
        <div class="input-group-prepend">
            <span class="input-group-text">{{ $symbol }}</span>
        </div>
        @endif
    @endif
    <input
        id="{{ $id }}"
        type="text"
        wire:ignore.self
        class="{{ $class }} currency-input"
        {!! $nameAttr !!}
        maxlength="{{ $maxlength }}"
        title="{{ $displayVal }}"
        data-target="#{{ $hidden }}"
        data-raw="{{ $rawVal }}"
        aria-label="{{ $ariaLabel }}"
        value="{{ $displayVal }}"
        onfocus="if(!this.dataset.bound){if(window.bindCurrencyInput){this.dataset.bound='1';window.bindCurrencyInput(this);}else if(window.currencyInputInit){window.currencyInputInit();}}"
        @if($hasDisabled) readonly @endif
        {{ $attributes->except(['disabled', 'symbol', 'position']) }}
    />
    @if($symbol)
        @if($position != 'prefix')
        <div class="input-group-append">
            <span class="input-group-text">{{ $symbol }}</span>
        </div>
        @endif
    </div>
    @endif

    <input
        type="hidden"
        id="{{ $hidden }}"
        value="{{ $hiddenVal }}"
        {!! $hiddenNameAttr !!}
        @if($wireModel)
            @if($wireModifier && $wireModifier !== '')
                wire:model.{{ $wireModifier }}="{{ $wireModel }}"
            @else
                wire:model="{{ $wireModel }}"
            @endif
        @endif
    />
</div>
