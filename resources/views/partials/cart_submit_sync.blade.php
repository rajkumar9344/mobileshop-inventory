{{--
    Shared partial: cart_submit_sync
    ────────────────────────────────
    Keeps the "Create" (submit) and "Save as Draft" buttons in sync with cart
    validity and DOM state.  Requires two Blade variables:
        $formId             – HTML id of the form element  (e.g. 'sale-form')
        $actionAvailabilityFn – window-level function name that re-evaluates
                              whether buttons should be enabled based on cart
                              content (e.g. 'updateSaleActionAvailability')
--}}
(function () {
    var formId              = '{{ $formId }}';
    var actionAvailabilityFn = '{{ $actionAvailabilityFn }}';

    function setSubmitDisabled(disabled) {
        var form = document.getElementById(formId);
        if (!form) return;
        var btns = form.querySelectorAll('button[type="submit"]');
        btns.forEach(function (b) { b.disabled = disabled; });
        var draftBtn = document.getElementById('save-draft-btn');
        if (draftBtn) draftBtn.disabled = disabled;
        // Re-evaluate product-based availability after each validity change
        if (typeof window[actionAvailabilityFn] === 'function') {
            window[actionAvailabilityFn]();
        }
    }

    // Start with buttons enabled (product-content guard is handled inside actionAvailabilityFn)
    setSubmitDisabled(false);

    // ── Livewire cart-validity event (server emits valid:true/false) ──────────
    function bindCartValidity() {
        try {
            Livewire.on('cart-validity', function (payload) {
                if (payload && typeof payload.valid !== 'undefined') {
                    setSubmitDisabled(!payload.valid);
                }
            });
        } catch (e) {}
    }

    if (window.Livewire) {
        bindCartValidity();
    } else {
        document.addEventListener('livewire:load', bindCartValidity);
    }

    // Browser-dispatched cart-validity custom event (legacy / non-Livewire path)
    window.addEventListener('cart-validity', function (e) {
        var payload = (e && e.detail) ? e.detail : {};
        if (payload && typeof payload.valid !== 'undefined') {
            setSubmitDisabled(!payload.valid);
        }
    });

    // ── DOM-based fallback: detect invalid rows in the cart table ─────────────
    var _timer = null;
    function updateFromDOM() {
        clearTimeout(_timer);
        _timer = setTimeout(function () {
            var invalidRow = document.querySelector('tr.invalid-row, tr[style*="#f8d7da"]');
            setSubmitDisabled(!!invalidRow);
        }, 60);
    }

    var cartContainer = document.querySelector('.table-responsive');
    if (cartContainer && window.MutationObserver) {
        var mo = new MutationObserver(updateFromDOM);
        mo.observe(cartContainer, { childList: true, subtree: true, attributes: true });
    }

    document.addEventListener('livewire:load',    updateFromDOM);
    document.addEventListener('livewire:update',  updateFromDOM);
    document.addEventListener('livewire:updated', updateFromDOM);

    if (window.Livewire && Livewire.hook) {
        try { Livewire.hook('message.processed', updateFromDOM); } catch (e) {}
    }

    updateFromDOM();
})();
