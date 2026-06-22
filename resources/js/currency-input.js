// Currency input behavior (paisa -> rupee auto-decimal)
// Centralises all currency formatting and Livewire sync logic.
(function () {

    // Small helpers for numeric normalization and formatting
    function stripTrailingZeroesDecimal(s) {
        if (typeof s !== 'string') s = String(s || '');
        if (s.indexOf('.') !== -1 && s.endsWith('.00')) return s.substring(0, s.length - 3);
        return s;
    }

    function cleanNumericString(s) {
        if (s === null || s === undefined) return '';
        s = String(s).trim();
        let sign = '';
        const matches = s.match(/[-+]/g);
        if (matches && matches.length > 0) {
            sign = matches[matches.length - 1];
        }
        s = s.replace(/[^0-9.]/g, '');
        s = s.replace(/(\..*)\./g, '$1');
        s = stripTrailingZeroesDecimal(s);
        if (!s) return sign;
        if (!s.includes('.')) s = String(parseInt(s, 10) || 0);
        return sign + s;
    }

    // Convert raw integer-string (e.g. "18308" or "183.08") to plain decimal string ("183.08").
    function rawToDecimal(raw) {
        if (!raw || raw === '-' || raw === '+') return raw;
        return cleanNumericString(raw);
    }

    // Format raw integer-string as localised display value (e.g. "1,83,08.00").
    function formatFromRaw(raw, el) {
        raw = String(raw || '');
        if (!raw || raw === '-' || raw === '+') return raw;
        const showSymbol = el && el.dataset && el.dataset.showSymbol === 'true';
        // Use cleaned numeric string to avoid duplicated logic
        raw = cleanNumericString(raw);
        if (!raw || raw === '-' || raw === '+') return raw;
        let sign = '';
        const matches = raw.match(/[-+]/g);
        if (matches && matches.length > 0) {
            sign = matches[matches.length - 1];
        }
        if (sign) raw = raw.replace(/[-+]/g, '');
        const [intPart, fracPart] = raw.split('.');
        const formattedInt = Number(Math.abs(parseInt(intPart || 0, 10))).toLocaleString('en-IN');
        const display = (typeof fracPart !== 'undefined') ? (formattedInt + '.' + (fracPart || '').substring(0, 2)) : formattedInt;
        return sign + (showSymbol ? '₹' + display : display);
    }

    // Write the current raw value into the paired hidden field.
    // Safe to call during typing — does NOT dispatch events or notify Livewire.
    // Livewire is notified once on blur via commitToLivewire() → Livewire.find().set().
    function syncHidden(el) {
        const hidden = el.dataset.target ? document.querySelector(el.dataset.target) : null;
        if (!hidden) return null;
        hidden.value = rawToDecimal(el.dataset.raw || '');
        try {
            hidden.setAttribute('value', hidden.value);
            hidden.defaultValue = hidden.value;
            // Do NOT dispatch 'input' here — that would fire wire:model on every keystroke,
            // causing per-character Livewire round-trips that invalidate cart rowIds and
            // replace the focused <tr>, losing the user's cursor mid-type (the 95.24 bug).
        } catch (e) {}
        return hidden;
    }

    // Sync the hidden field and notify Livewire. Only called on blur after a real change.
    function commitToLivewire(el) {
        if (el.readOnly) return;
        const hidden = syncHidden(el);
        if (!hidden) return;
        try { hidden.dispatchEvent(new Event('blur', { bubbles: true })); } catch (e) {}
        try {
            if (window.Livewire && typeof window.Livewire.find === 'function') {
                const compRoot = el.closest('[wire\\:id]') || document.querySelector('[wire\\:id]');
                if (compRoot) {
                    const compId = compRoot.getAttribute('wire:id');
                    const wm = Array.from(hidden.getAttributeNames()).find(n => n.startsWith('wire:model'));
                    const propName = wm ? hidden.getAttribute(wm) : null;
                    if (compId && propName) {
                        window.Livewire.find(compId).set(propName, hidden.value);
                    }
                }
            }
        } catch (e) {}
    }

    function bindCurrencyInput(el) {
        const hidden = el.dataset.target ? document.querySelector(el.dataset.target) : null;

        // Server normalization: hidden.value is the canonical rupee string when present.
        // Some server code writes a data-raw attribute as paisa (integer). Handle both.
        function serverRawToRupee(hiddenVal, dataRawAttr) {
            if (hiddenVal) return cleanNumericString(String(hiddenVal).replace(/,/g, ''));
            if (!dataRawAttr) return '';
            let s = String(dataRawAttr).trim();
            // If the server already provided a decimal string, use cleaned version
            if (s.indexOf('.') !== -1) return cleanNumericString(s);
            // Otherwise assume paisa integer and convert to rupee
            s = s.replace(/[^0-9\-+]/g, '');
            const sign = s.startsWith('-') ? '-' : (s.startsWith('+') ? '+' : '');
            if (sign) s = s.substring(1);
            const n = parseInt(s, 10);
            if (isNaN(n)) return sign;
            let rupee = (n / 100).toFixed(2);
            rupee = stripTrailingZeroesDecimal(rupee);
            return sign + rupee;
        }

        // Initialise display value from existing hidden field (page load / Livewire re-render)
        if (hidden || el.getAttribute('data-raw')) {
            const hiddenVal = hidden ? String(hidden.value || '') : '';
            const dataRawAttr = el.getAttribute('data-raw') || '';
            const initial = serverRawToRupee(hiddenVal, dataRawAttr);
            if (initial) {
                el.dataset.raw = initial;
                el.value = formatFromRaw(el.dataset.raw, el);
            }
        }

        el.addEventListener('input', function () {
            const v = this.value || '';
            let cleaned = v.replace(/[^0-9.\-+]/g, '');
            cleaned = cleaned.replace(/(\..*)\./g, '$1');
            let sign = '';
            const matches = cleaned.match(/[-+]/g);
            if (matches && matches.length > 0) {
                sign = matches[matches.length - 1];
            }
            cleaned = cleaned.replace(/[-+]/g, '');
            if (cleaned === '') cleaned = '';
            this.dataset.raw = sign + cleaned;
            this.value = formatFromRaw(this.dataset.raw, this);
            syncHidden(this); // keep hidden up-to-date while typing; Livewire notified on blur
        });

        el.addEventListener('focus', function () {
            this.dataset.rawOnFocus = this.dataset.raw || ''; // snapshot for change detection on blur
            const raw = this.dataset.raw || '';
            if (!raw) { this.value = ''; return; }
            // Show a compact editable value on focus (hide trailing .00)
            try {
                this.value = stripTrailingZeroesDecimal(String(raw).replace(/,/g, ''));
            } catch (e) {
                this.value = raw.replace(/,/g, '');
            }
            try { this.setSelectionRange(this.value.length, this.value.length); } catch (e) {}
        });

        el.addEventListener('blur', function () {
            this.value = this.dataset.raw ? formatFromRaw(this.dataset.raw, this) : '';
            const changed = (this.dataset.raw || '') !== (this.dataset.rawOnFocus || '');
            delete this.dataset.rawOnFocus;
            if (changed) commitToLivewire(this);
        });
    }

    function initAll() {
        document.querySelectorAll('input.currency-input').forEach(function (el) {
            // Strip residual currency symbols left by server render
            if (el.value && /[\u20B9$€£]/.test(el.value)) {
                el.value = el.value.replace(/[^0-9\-.,]/g, '').trim();
            }
            if (el.dataset.bound) return;
            el.dataset.bound = '1';
            bindCurrencyInput(el);
        });
    }

    document.addEventListener('DOMContentLoaded', initAll);
    if (window.Livewire && window.Livewire.hook) {
        window.Livewire.hook('message.processed', function () { setTimeout(initAll, 10); });
    }

    // Expose globally: currencyInputInit for Livewire-triggered full scan,
    // bindCurrencyInput for targeted single-element init from the blade component.
    window.currencyInputInit = initAll;
    window.bindCurrencyInput = bindCurrencyInput;

}());
