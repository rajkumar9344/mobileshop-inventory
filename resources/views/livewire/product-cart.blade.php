<div>
    @php $isReadOnly = $this->readonly ?? false; @endphp
    <style>
        /* Component-scoped layout tweaks - responsive-friendly
           - Prefer fluid/responsive widths instead of a large fixed min-width
           - Use clamp() so inputs scale between reasonable min/max sizes
           - Keep product name wrapping while numeric columns stay on one line
        */

        /* Make the table allow horizontal scrolling when necessary, but avoid forcing a very large min-width */
        .table-responsive > .product-cart-table {
            min-width: 900px; /* smaller baseline so UI is not too wide */
            width: 100%;
            table-layout: auto;
        }

        .product-cart-table th,
        .product-cart-table td {
            vertical-align: middle !important;
        }

        /* Product name cell: allow wrapping so long names don't push table width */
        .product-cart-table td.product-name {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            /* make the product column slightly wider so product names don't wrap too early */
            min-width: 12ch;
            max-width: 32ch; /* ~320px depending on font-size */
            width: clamp(12ch, 18vw, 32ch);
        }

        /* Numeric/short fields should not wrap */
        .product-cart-table td.numeric,
        .product-cart-table th.numeric {
            white-space: nowrap;
        }

        /* Responsive input/select sizes — width fills cell; column classes set cell width */
        .product-cart-table td input.form-control,
        .product-cart-table td select.form-control {
            max-width: 11rem;
            box-sizing: border-box;
        }

        /* Smaller inputs for fields that accept only a few digits */
        .product-cart-table td.small-field input.form-control,
        .product-cart-table td.small-field select.form-control {
            width: clamp(2.5rem, 3.5vw, 4.5rem);
            max-width: 5.5rem;
            box-sizing: border-box;
            text-align: center;
        }

        /* Cash discount amount input: inherits 100% width from general rule below; fixed by column class */

        /* Slightly reduce padding for table cells to fit more at 100% without breaking 80% */
        .product-cart-table th,
        .product-cart-table td {
            padding: .55rem .45rem;
        }

        /* Smooth horizontal scrolling on touch devices */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Loading overlay moved from inline style to class so CSS controls appearance */
        .cart-loading-overlay {
            top: 0;
            right: 0;
            left: 0;
            bottom: 0;
            background-color: rgba(255,255,255,0.5);
            z-index: 99;
        }

        /* Truncate long numeric/display values so they don't force table expansion */
        .product-cart-table td input.form-control,
        .product-cart-table td select.form-control,
        .product-cart-table td .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Amount column: show full numeric value (no ellipsis) but allow autosize to set width */
        .product-cart-table td.amount-col input.form-control {
            overflow: visible;
            text-overflow: clip;
            white-space: nowrap;
            text-align: right;
        }

        .product-cart-table td .truncate {
            display: inline-block;
            max-width: 140px;
            margin: 0 auto;
        }

        /* Remove spinner arrows from number inputs */
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        /* Ensure select dropdowns display options properly */
        select.form-control {
            min-width: 50px;
        }

        /* Common column classes (use these on <th> and <td>) */
        .col-amount {
            width: 120px;
            min-width: 120px;
            text-align: right;
        }

        .col-percent {
            width: 60px;
            min-width: 60px;
            text-align: center;
        }

        .col-small {
            width: 75px;
            min-width: 75px;
            text-align: center;
        }

        .col-cash {
            width: 105px;
            min-width: 105px;
            max-width: 105px;
            text-align: right;
        }

        /* Cash discount amount input: fixed width, truncate overflow */
        .product-cart-table td.cash-dis-field input.form-control,
        .product-cart-table td.cash-dis-field .form-control {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Make inputs fill their table cell so column widths are authoritative */
        .product-cart-table td input.form-control,
        .product-cart-table td select.form-control {
            width: 100%;
            box-sizing: border-box;
        }

        /* Highlight invalid cart rows (stronger than bootstrap to avoid overrides) */
        .invalid-row, tr.invalid-row > td {
            background-color: #f8d7da !important;
        }

        select.form-control option {
            white-space: nowrap;
            overflow: visible;
        }

        /* Product code column: allow select to autosize to content and badges to truncate with tooltip */
        .product-cart-table td.product-code select.form-control {
            width: auto !important;
            min-width: 4.5rem;
            max-width: 18rem;
            display: inline-block;
        }

        .product-cart-table td.product-code .badge {
            display: inline-block;
            max-width: 16rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
    <div>
        @if (session()->has('message') && empty($validation_message))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="alert-body">
                    <span>{{ session('message') }}</span>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            </div>
        @endif
        @if(!empty($validation_message))
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="cart-validation-alert">
                <div class="alert-body">
                    <strong>Warning:</strong> {{ $validation_message }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            </div>
        @endif
        {{-- Server-authoritative invalid product IDs — updated by Livewire on every re-render --}}
        <div id="cart-invalid-row-ids"
             wire:key="cart-invalid-row-ids"
             data-invalid-ids="{{ json_encode($invalid_row_ids ?? []) }}"
             style="display:none;"></div>
        
        <div class="table-responsive position-relative">
            @php $isPurchaseType4 = in_array($cart_instance, ['purchase','purchase_edit','purchase_return','purchase_view','purchase_return_view']) && $purchase_type == 4; @endphp
            <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center cart-loading-overlay">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
            <table class="table table-bordered product-cart-table">
                <thead class="thead-dark">
                <tr>
                    <th class="align-middle col-product-name">Product Name</th>
                    <th class="align-middle text-center col-product-code">Product Code</th>
                    <th class="align-middle text-center">Category</th>
                    <th class="align-middle text-center col-small">
                    @if(in_array($cart_instance, ['purchase_return', 'purchase_return_view']))
                        Purchased Qty
                    @else
                        Stock
                    @endif
                </th>
                    @if(in_array($cart_instance, ['purchase','purchase_edit','purchase_return']))
                    <th class="align-middle text-center col-small">M/N/L</th>
                    @endif
                    <th class="align-middle text-center col-amount">MRP</th>
                    <th class="align-middle text-center col-percent">Tax %</th>
                    <th class="align-middle text-center col-amount">Rate before Discount</th>
                    <th class="align-middle text-center col-small">Quantity</th>
                    <th class="align-middle text-center col-small">Unit</th>
                    <th class="align-middle text-center col-amount">@if(in_array($cart_instance, ['sale','sale_edit','sale_return','quotation','quotation_edit','sale_view','sale_return_view','quotation_view']) || $isPurchaseType4) Total @else Total (without GST) @endif</th>
                    <th class="align-middle text-center col-percent">GST %</th>
                    @unless(in_array($cart_instance, ['sale','sale_edit','sale_return','quotation','quotation_edit','sale_view','sale_return_view','quotation_view']) || $isPurchaseType4)
                    <th class="align-middle text-center col-amount">Amount (including GST)</th>
                    @endunless
                    @unless($isReadOnly)
                    <th class="align-middle text-center col-small">Action</th>
                    @endunless
                </tr>
                </thead>
                <tbody>
                    @if($cart_items->isNotEmpty())
                        @foreach($cart_items as $cart_item)
                            @php
                                $_mrp_val              = (float)($mrp[$cart_item->id] ?? $cart_item->options->mrp ?? 0);
                                // Tax % (col 7): used only for Rate before Discount = MRP / (1 + tax%)
                                $_tax_pct              = (float)($tax_percent_edit[$cart_item->id] ?? $cart_item->options->tax_percent ?? 0);
                                // GST % (last col): used for Tax Amount and Amount incl. GST
                                $_gst_pct              = (float)($gst_percent[$cart_item->id] ?? $cart_item->options->gst_percent ?? $_tax_pct);
                                // For sale/sale_return/quotation: allow user override via rate property if set, else derive from MRP.
                                // This matches purchase/purchase_return logic and ensures editable fields don't revert on blur.
                                if (false) { // Disabled strict MRP derivation to allow user edits
                                    $_rate_before_discount_precise = $_tax_pct > 0
                                        ? ($_mrp_val / (1 + $_tax_pct / 100))
                                        : $_mrp_val;
                                } else {
                                    // If the Livewire `rate` array contains an entry for
                                    // this product (even if zero) treat it as authoritative.
                                    if (is_array($rate) && array_key_exists($cart_item->id, $rate)) {
                                        $_rate_before_discount_precise = (float) $rate[$cart_item->id];
                                    } elseif (isset($cart_item->options->rate_before_discount)) {
                                        $_rate_before_discount_precise = (float) $cart_item->options->rate_before_discount;
                                    } else {
                                        $_rate_before_discount_precise = (($_tax_pct > 0) ? ($_mrp_val / (1 + $_tax_pct / 100)) : $_mrp_val);
                                    }
                                }
                                // Rounded value used for display only
                                $_rate_before_discount = round($_rate_before_discount_precise, 2);

                                $_item_discount_val    = (float)($item_discount[$cart_item->id] ?? $cart_item->options->product_discount_percent ?? $cart_item->options->discount ?? 0);

                                // Rate after Discount % (first column)
                                // For sale / sale_return / quotation apply percent discount on MRP
                                if (in_array($cart_instance, ['sale', 'sale_edit', 'sale_return', 'quotation', 'quotation_edit', 'sale_view', 'sale_return_view', 'quotation_view'])) {
                                    $_rate_after_pct = $_mrp_val * (1 - $_item_discount_val / 100);
                                } else {
                                    $_rate_after_pct = $_rate_before_discount_precise * (1 - $_item_discount_val / 100);
                                }

                                // Cash discount: percent-based + fixed amount (mirrors getEffectiveCashDiscountAmount)
                                $_cash_disc_pct        = (float)($cash_discount_percent[$cart_item->id] ?? $cart_item->options->cash_discount_percent ?? 0);
                                $_cash_disc_amt        = (float)($cash_discount_amount[$cart_item->id] ?? $cart_item->options->cash_discount_amount ?? 0);

                                // intermediate rate after applying cash percentage only (second column preview)
                                $_rate_after_cash_pct  = round($_rate_after_pct - ($_cash_disc_pct > 0 ? $_rate_after_pct * $_cash_disc_pct / 100 : 0), 2);

                                // total cash discount amount (percent + fixed)
                                $_cash_discount_total  = ($_cash_disc_pct > 0 ? $_rate_after_pct * $_cash_disc_pct / 100 : 0) + $_cash_disc_amt;

                                // Net Rate = rate_after_pct_discount − cash_discount (all pre-tax)
                                $_net_rate             = round($_rate_after_pct - $_cash_discount_total, 2);
                                $_total_without_gst    = round($_net_rate * $cart_item->qty, 2);
                                $_tax_amount_display   = round($_total_without_gst * $_gst_pct / 100, 2);
                            @endphp
                            @php
                                // Compare against product ID (integer) — deterministic, matches updateValidity()
                                $_isInvalidRow = isset($invalid_row_ids) && is_array($invalid_row_ids) && in_array((int)$cart_item->id, $invalid_row_ids);
                            @endphp
                            <tr wire:key="{{ $cart_item->rowId }}"
                                id="cart-row-{{ $cart_item->rowId }}"
                                data-product-id="{{ $cart_item->id }}"
                                class="{{ $_isInvalidRow ? 'table-danger invalid-row' : '' }}">
                                <td class="align-middle product-name col-product-name">
                                    @if(!$isReadOnly && str_contains(strtolower($cart_item->name), 'silicon'))
                                        <input type="text"
                                            class="form-control form-control-sm"
                                            wire:model.blur="custom_product_names.{{ $cart_item->rowId }}"
                                            placeholder="{{ $cart_item->name }}"
                                            title="You can customise the product name for this item">
                                    @else
                                        {{ $cart_item->name }}
                                    @endif
                                </td>

                                <td class="align-middle text-center product-code col-product-code">
                                    @php
                                        $_avail_codes = $product_codes[$cart_item->id] ?? [];
                                    @endphp
                                    @if(count($_avail_codes) > 1 && !$isReadOnly)
                                        <select wire:model.live="selected_code.{{ $cart_item->id }}"
                                                    class="form-control form-control-sm autosize">
                                            @foreach($_avail_codes as $_c)
                                                <option value="{{ $_c }}" {{ ($selected_code[$cart_item->id] ?? $cart_item->options->code) === $_c ? 'selected' : '' }}>
                                                    {{ $_c }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="badge badge-success">
                                            {{ $selected_code[$cart_item->id] ?? $cart_item->options->code }}
                                        </span>
                                    @endif
                                </td>

                                <td class="align-middle text-center">
                                    {{ $cart_item->options->category ?? '-' }}
                                </td>

    <script>
        (function(){
            function measureTextWidth(text, font) {
                const canvas = measureTextWidth._canvas || (measureTextWidth._canvas = document.createElement('canvas'));
                const ctx = canvas.getContext('2d');
                ctx.font = font || getComputedStyle(document.body).font;
                return ctx.measureText(text).width;
            }

            function autosizeProductCodeFields(root=document) {
                const rows = root.querySelectorAll('.product-cart-table td.product-code');
                rows.forEach(td => {
                    const select = td.querySelector('select.form-control');
                    const badge = td.querySelector('.badge');
                    if (select) {
                        // calculate the widest option text
                        let maxW = 0;
                        const style = getComputedStyle(select);
                        const font = style.font || `${style.fontSize} ${style.fontFamily}`;
                        for (let opt of select.options) {
                            const w = measureTextWidth(opt.text, font);
                            if (w > maxW) maxW = w;
                        }
                        // add padding + arrow room
                        const desired = Math.min(Math.max(maxW + 36, 72), 288);
                        select.style.width = desired + 'px';
                        select.title = select.options[select.selectedIndex]?.text || '';
                    }
                    if (badge) {
                        badge.title = badge.textContent.trim();
                    }
                });
            }

            // Run on initial load
            document.addEventListener('DOMContentLoaded', function(){ autosizeProductCodeFields(); });

            // Also run after Livewire updates (message.processed)
            document.addEventListener('livewire:load', function() {
                if (window.livewire) {
                    window.livewire.hook('message.processed', () => {
                        autosizeProductCodeFields();
                    });
                }
            });

            // MutationObserver fallback: watch table body for changes
            const table = document.querySelector('.product-cart-table');
            if (table) {
                const mo = new MutationObserver(() => autosizeProductCodeFields(table));
                mo.observe(table, { childList: true, subtree: true });
            }
        })();
    </script>

                                <td class="align-middle text-center">
                                    <span class="badge badge-info">{{ $cart_item->options->stock }}</span>
                                </td>

                                @if(in_array($cart_instance, ['purchase','purchase_edit','purchase_return']))
                                <td class="align-middle text-center col-small">
                                    <select wire:model.lazy="rate_type.{{ $cart_item->id }}"
                                            class="form-control form-control-sm autosize"
                                            {{ $isReadOnly ? 'disabled' : '' }}>
                                        <option value="N" {{ ($rate_type[$cart_item->id] ?? 'M') === 'N' ? 'selected' : '' }}>N</option>
                                        <option value="M" {{ ($rate_type[$cart_item->id] ?? 'M') === 'M' ? 'selected' : '' }}>M</option>
                                        <option value="L" {{ ($rate_type[$cart_item->id] ?? 'M') === 'L' ? 'selected' : '' }}>L</option>
                                    </select>
                                </td>
                                @endif

                                <td class="align-middle text-center col-amount">
                                    <x-currency-input
                                        id="{{ 'mrp_'.$cart_item->id }}"
                                        wireModel="{{ 'mrp.'.$cart_item->id }}"
                                        class="form-control form-control-sm autosize"
                                        display="{{ format_currency($mrp[$cart_item->id] ?? $cart_item->options->mrp ?? 0, true, false) }}"
                                        wire:key="mrp-{{ $cart_item->id }}-{{ $rate_type[$cart_item->id] ?? 'M' }}"
                                        :disabled="$isReadOnly"
                                    />
                                </td>

                                <td class="align-middle text-center small-field numeric col-percent">
                                    <input type="number"
                                           wire:model.lazy="tax_percent_edit.{{ $cart_item->id }}"
                                           class="form-control form-control-sm autosize"
                                           step="0.01"
                                           min="0"
                                           max="100"
                                           maxlength="6"
                                           value="{{ $tax_percent_edit[$cart_item->id] ?? $cart_item->options->tax_percent ?? 0 }}"
                                           {{ $isReadOnly ? 'readonly' : '' }}>
                                </td>

                                <td class="align-middle text-center col-amount">
                                    <x-currency-input
                                        id="{{ 'rate_'.$cart_item->id }}"
                                        wireModel="{{ 'rate.'.$cart_item->id }}"
                                        class="form-control form-control-sm autosize"
                                        display="{{ format_currency(in_array($cart_instance, ['sale', 'sale_edit', 'sale_return', 'quotation', 'quotation_edit', 'sale_view', 'sale_return_view', 'quotation_view']) ? $_rate_before_discount : ($rate[$cart_item->id] ?? $_rate_before_discount ?? 0), true, false) }}"
                                        title="Rate before Discount = MRP / (1 + Tax%/100)."
                                        wire:key="rate-{{ $cart_item->id }}"
                                        :disabled="$isReadOnly"
                                    />
                                </td>

                                <td class="align-middle text-center col-small">
                                    @include('livewire.includes.product-cart-quantity')
                                </td>

                                <td class="align-middle text-center">
                                    {{ $cart_item->options->unit }}
                                </td>

                                <td class="align-middle text-center amount-col col-amount">
                                    {{-- Total (pre-tax) = pre-tax Net Rate × Qty. For sale flows this column is labelled 'Total' and represents the line total. --}}
                                    <input type="text"
                                           class="form-control form-control-sm autosize full-autosize"
                                           value="{{ format_currency($_total_without_gst, true, false) }}"
                                           readonly
                                           maxlength="15"
                                           title="{{ format_currency($_total_without_gst, true, false) }}"
                                           >
                                </td>

                                <td class="align-middle text-center small-field numeric col-percent">
                                    {{-- GST % editable; computed Tax Amount shown below --}}
                                    <input type="number"
                                           wire:model.lazy="gst_percent.{{ $cart_item->id }}"
                                           class="form-control form-control-sm autosize"
                                           step="0.01"
                                           min="0"
                                           max="100"
                                           maxlength="6"
                                           value="{{ $gst_percent[$cart_item->id] ?? $cart_item->options->gst_percent ?? $cart_item->options->tax_percent ?? 0 }}"
                                           {{ $isReadOnly ? 'readonly' : '' }}>
                                    @if($_tax_amount_display > 0)
                                        <small class="text-muted d-block mt-1" title="Tax Amount = Total (w/o GST) × {{ $_gst_pct }}%">
                                            {{ format_currency($_tax_amount_display, true, false) }}
                                        </small>
                                    @endif
                                </td>

                                @unless(in_array($cart_instance, ['sale','sale_edit','sale_return','quotation','quotation_edit','sale_view','sale_return_view','quotation_view']) || $isPurchaseType4)
                                <td class="align-middle text-center">
                                    {{-- Amount incl. GST = Total (w/o GST) + Tax Amount, computed from blade vars --}}
                                    @php $_amount_incl_gst = $_total_without_gst + $_tax_amount_display; @endphp
                                    <div class="truncate" title="{{ format_currency($_amount_incl_gst, true, false) }}">{{ format_currency($_amount_incl_gst, true, false) }}</div>
                                </td>
                                @endunless

                                @unless($isReadOnly)
                                <td class="align-middle text-center">
                                    <a href="#" wire:click.prevent="removeItem('{{ $cart_item->rowId }}')">
                                        <i class="bi bi-x-circle font-2xl text-danger"></i>
                                    </a>
                                </td>
                                @endunless
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            @php
                                $colspan = 0;
                                if (in_array($cart_instance, ['purchase', 'purchase_edit', 'purchase_return', 'purchase_view', 'purchase_return_view'])) {
                                    $colspan = $isPurchaseType4 ? 17 : 18;
                                } elseif (in_array($cart_instance, ['sale', 'sale_edit', 'sale_return', 'quotation', 'quotation_edit', 'sale_view', 'sale_return_view', 'quotation_view'])) {
                                    // removed HSN, Discount%, Additional Discount%, Cash Discount, Net Rate (per-row) columns
                                    $colspan = 11;
                                } else {
                                    $colspan = 17;
                                }
                                $colspan = $colspan - ($isReadOnly ? 1 : 0);
                            @endphp
                            <td colspan="{{ $colspan }}" class="text-center">
                        <span class="text-danger">
                            Please search & select products!
                        </span>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <script>
        /**
         * Product Cart UI Utilities
         * - Autosize inputs based on content
         * - HSN column width adjustment
         * - Livewire integration
         */
        (function() {
            // Shared canvas for text measurement (reused across functions)
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            function getFont(el) {
                const s = window.getComputedStyle(el);
                return `${s.fontWeight || '400'} ${s.fontSize || '14px'} ${s.fontFamily || 'sans-serif'}`;
            }

            function measureText(text, font) {
                ctx.font = font;
                return Math.ceil(ctx.measureText(text).width);
            }

            // ========== Input Autosize ==========
            function resizeInput(el) {
                if (!el) return;
                const val = el.value || el.placeholder || el.getAttribute('value') || '';
                const font = getFont(el);
                const textWidth = measureText(String(val), font);
                const td = el.closest('td');
                
                let minPx = 38, maxPx = 160;
                
                if (el.classList.contains('full-autosize') || td?.classList.contains('amount-col')) {
                    minPx = 80;
                    maxPx = 900;
                }

                // Only set explicit inline width for large amount fields.
                // cash-dis-field and other fields use CSS width: 100% for uniform sizing.
                if (el.classList.contains('full-autosize') || td?.classList.contains('amount-col')) {
                    el.style.width = `${Math.min(maxPx, Math.max(minPx, textWidth + 18))}px`;
                } else {
                    // Clear any inline width to let CSS handle uniform sizing
                    el.style.width = '';
                }
            }

            function bindAutosize(root) {
                (root || document).querySelectorAll('input.autosize, select.autosize').forEach(el => {
                    if (el.dataset.autosizeBound) return;
                    el.dataset.autosizeBound = '1';
                    resizeInput(el);
                    el.addEventListener('input', () => resizeInput(el));
                    el.addEventListener('focus', () => resizeInput(el));
                });
            }

            // ========== Initialization ==========
            function initAll() {
                bindAutosize(document);
                if (window.updateHiddenFields) window.updateHiddenFields();
            }

            document.addEventListener('DOMContentLoaded', () => {
                initAll();
                setTimeout(initAll, 300); // Catch late-rendered content
            });

            // Livewire integration
            if (window.Livewire?.hook) {
                window.Livewire.hook('message.processed', () => setTimeout(initAll, 50));
                window.Livewire.hook('element.updated', (el) => {
                    el.querySelectorAll('input.autosize, select.autosize').forEach(input => {
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                });
            }

            document.addEventListener('livewire:updated', () => {
                if (window.updateHiddenFields) window.updateHiddenFields();
            });
        })();

        /**
         * Cart Row Highlight — JS safety net
         *
         * The primary highlighting is server-rendered via inline style on each <tr>.
         * Livewire morph preserves those inline styles on every re-render, so no JS
         * timing tricks are needed.  This small script reinforces the inline styles
         * by reading the server-authoritative #cart-invalid-row-ids[data-invalid-ids]
         * store element and re-adding the class + style for any rows that lost them
         * (belt-and-suspenders approach).
         */
        (function() {
            function enforceHighlights() {
                try {
                    var store = document.getElementById('cart-invalid-row-ids');
                    var ids = [];
                    if (store) {
                        try { ids = JSON.parse(store.getAttribute('data-invalid-ids') || '[]'); } catch(e) { ids = []; }
                    }
                    // ids now contains product IDs (integers); rows have id="cart-row-{rowId}"
                    // So we match via data-product-id attribute set on each <tr>, OR we scan all rows
                    var allRows = document.querySelectorAll('tr[id^="cart-row-"]');
                    allRows.forEach(function(tr) {
                        var pid = parseInt(tr.getAttribute('data-product-id') || '0', 10);
                        if (ids.indexOf(pid) !== -1) {
                            tr.classList.add('invalid-row', 'table-danger');
                            tr.style.setProperty('background-color', '#f8d7da', 'important');
                        } else {
                            tr.classList.remove('invalid-row', 'table-danger');
                            tr.style.removeProperty('background-color');
                        }
                    });
                } catch(e) { /* ignore */ }
            }

            // Run on every Livewire round-trip (hook available in both v2 and v3)
            if (window.Livewire && window.Livewire.hook) {
                window.Livewire.hook('message.processed', enforceHighlights);
            }
            // Livewire v3 custom event
            document.addEventListener('livewire:updated',    enforceHighlights);
            document.addEventListener('livewire:load',       enforceHighlights);
            document.addEventListener('DOMContentLoaded',    enforceHighlights);
        })();

        /**
         * Form-Submit Gate
         *
         * Problem: wire:model.lazy syncs on blur. When the user types a discount %
         * and immediately clicks Save, two things happen concurrently:
         *   1. blur  -> Livewire XHR: runs updatedItemDiscount() -> updateItemPrice()
         *              -> writes product_discount_percent to the cart session
         *   2. click -> form AJAX POST -> controller reads Cart session
         * If (2) reaches the server before (1) finishes writing, the controller
         * sees the stale session (product_discount_percent = 0 or missing) and
         * saves discount_percent = 0 to the database.
         *
         * Fix: intercept every form submit in capture phase (before jQuery sees it),
         * blur the active Livewire input, wait for all in-flight Livewire XHRs to
         * complete (max 3 s), then re-fire the submit via jQuery so the existing
         * AJAX handler runs with the fully-updated cart session.
         */
        (function () {
            var _pending = 0;

            function hookLivewire() {
                if (typeof Livewire === 'undefined' || !Livewire.hook) return;
                Livewire.hook('message.sent',      function () { _pending++; });
                Livewire.hook('message.processed', function () { _pending = Math.max(0, _pending - 1); });
                Livewire.hook('message.failed',    function () { _pending = Math.max(0, _pending - 1); });
            }

            function waitIdle(maxMs) {
                return new Promise(function (resolve) {
                    var deadline = Date.now() + (maxMs || 3000);
                    (function check() {
                        if (_pending <= 0 || Date.now() >= deadline) resolve();
                        else setTimeout(check, 30);
                    })();
                });
            }

            document.addEventListener('submit', function (e) {
                var form = e.target;

                // Re-dispatched form — let it through
                if (form.__lwGated) { delete form.__lwGated; return; }

                var ae  = document.activeElement;
                var inLW = ae && ae !== document.body &&
                           typeof ae.closest === 'function' &&
                           ae.closest('[wire\\:id]');

                // Nothing pending and active element is not inside Livewire — no delay needed
                if (_pending <= 0 && !inLW) return;

                e.preventDefault();
                e.stopImmediatePropagation();

                // Trigger wire:model.lazy sync immediately
                if (ae && ae !== document.body) ae.blur();

                waitIdle(3000).then(function () {
                    form.__lwGated = true;
                    if (window.jQuery) {
                        jQuery(form).trigger('submit');
                    } else {
                        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                    }
                });
            }, true /* capture phase */);

            hookLivewire();
            document.addEventListener('DOMContentLoaded', hookLivewire);
            document.addEventListener('livewire:load',    hookLivewire);
        })();
    </script>

    <!-- Overall Calculations Section -->
    <div class="border p-3 mb-3">
        <h5>Overall Calculations</h5>
        <div class="form-row">
            <div class="col-md-2 pr-1">
                <label for="overall_nos">Nos</label>
                <input type="text" class="form-control" name="overall_nos" id="overall_nos" value="{{ $this->overall_calculations['overall_nos'] }}" readonly>
            </div>
            <div class="col-md-2 pr-1">
                <label for="overall_quantity">Quantity</label>
                <input type="text" class="form-control" name="overall_quantity" id="overall_quantity" value="{{ $this->overall_calculations['overall_quantity'] }}" readonly>
            </div>
            <div class="col-md-2 pr-1">
                <label for="overall_gross_amount">Gross Amount</label>
                <input type="text" class="form-control" name="overall_gross_amount" id="overall_gross_amount" value="{{ format_currency($this->overall_calculations['overall_gross_amount'], true, false) }}" readonly>
            </div>
            <div class="col-md-2 pr-1">
                <label for="overall_taxable_amount">Taxable Amount</label>
                <input type="text" class="form-control" name="overall_taxable_amount" id="overall_taxable_amount" value="{{ format_currency($this->overall_calculations['overall_taxable_amount'], true, false) }}" readonly>
            </div>
            <div class="col-md-2 pr-1">
                <label for="overall_tax_amount">Tax Amount</label>
                <input type="text" class="form-control" name="overall_tax_amount" id="overall_tax_amount" value="{{ format_currency($this->overall_calculations['overall_tax_amount'], true, false) }}" readonly>
            </div>
            <div class="col-md-2 pr-1">
                <label for="overall_amount">Total Amount</label>
                <input type="text" class="form-control" name="overall_amount" id="overall_net_rate" value="{{ format_currency(in_array($cart_instance, ['sale','sale_edit','sale_return','quotation','quotation_edit','sale_view','sale_return_view','quotation_view']) ? round($this->overall_calculations['overall_amount'] ?? 0, 0) : ($this->overall_calculations['overall_amount'] ?? 0), true, false) }}" readonly>
            </div>
        </div>
    </div>

    <!-- Currency behavior provided by resources/js/currency-input.js -->
</div>
