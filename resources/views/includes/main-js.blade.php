<script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Moment and Daterangepicker (used by date filters) -->
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
@vite('resources/js/app.js')
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script defer src="https://cdn.datatables.net/v/bs4/jszip-3.10.1/dt-1.13.5/b-2.4.1/b-html5-2.4.1/b-print-2.4.1/sl-1.7.0/datatables.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/1.4.0/perfect-scrollbar.js"></script>
<script defer src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>
{{--
    DataTables error handling + session keep-alive.
    Deferred so it runs after datatables.min.js but before any DataTable is initialised.
--}}
<script defer>
(function () {
    /* ── 1. SESSION KEEP-ALIVE ──────────────────────────────────────────────
       Ping /ping every 5 minutes while the tab is visible to prevent session
       expiry on active pages. Pauses when the tab is hidden (no wasted requests). */
    var PING_INTERVAL_MS = 5 * 60 * 1000; // 5 minutes
    var pingTimer = null;

    function startPing() {
        if (pingTimer) return;
        pingTimer = setInterval(function () {
            if (document.visibilityState === 'hidden') return;
            fetch('/ping', { credentials: 'same-origin' }).catch(function () {});
        }, PING_INTERVAL_MS);
    }

    document.addEventListener('DOMContentLoaded', startPing);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') startPing();
    });

    /* ── 2. DATATABLES ERROR HANDLER ────────────────────────────────────────
       Replace the native blocking alert() with an inline dismissible banner
       above the affected table.
         • 401 / 419 (session expired / CSRF) → "Session expired" + Login button
         • Any other error                    → "Could not load" + Reload button   */
    function setupDtErrorHandler() {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.dataTable) return;

        jQuery.fn.dataTable.ext.errMode = 'none';

        jQuery(document).on('error.dt', function (e, settings, techNote, message) {
            var tableId  = (settings && settings.sTableId) ? settings.sTableId : null;
            var $table   = tableId ? jQuery('#' + tableId) : null;
            var $wrapper = $table && $table.length
                ? $table.closest('.card-body, .dataTables_wrapper, .table-responsive').first()
                : null;

            // Detect session-expiry vs generic error from the XHR status code
            var xhr      = settings && settings.jqXHR;
            var status   = xhr ? xhr.status : 0;
            var isExpiry = (status === 401 || status === 419);

            var bannerId = 'dt-error-banner-' + (tableId || 'global');

            // Only one banner per table at a time
            jQuery('#' + bannerId).remove();

            var html;
            if (isExpiry) {
                html = '<div id="' + bannerId + '" class="alert alert-warning alert-dismissible d-flex align-items-center mb-2" role="alert" style="font-size:.875rem;">'
                     + '<i class="bi bi-clock mr-2"></i>'
                     + '<span>Your session has expired.</span>'
                     + '<a href="/login" class="btn btn-sm btn-warning ml-auto mr-2">Login again</a>'
                     + '<button type="button" class="close ml-0" data-dismiss="alert"><span>&times;</span></button>'
                     + '</div>';
            } else {
                html = '<div id="' + bannerId + '" class="alert alert-danger alert-dismissible d-flex align-items-center mb-2" role="alert" style="font-size:.875rem;">'
                     + '<i class="bi bi-exclamation-triangle mr-2"></i>'
                     + '<span>Could not load latest data.</span>'
                     + '<button class="btn btn-sm btn-outline-danger ml-auto mr-2 dt-reload-btn" data-table="' + (tableId || '') + '">&#8635; Reload</button>'
                     + '<button type="button" class="close ml-0" data-dismiss="alert"><span>&times;</span></button>'
                     + '</div>';
            }

            if ($wrapper && $wrapper.length) {
                $wrapper.prepend(html);
            } else {
                jQuery('main.c-main, .c-body, body').first().prepend(html);
            }

            console.warn('[DataTables] AJAX error (table: ' + (tableId || '?') + ', status: ' + status + '):', message);
        });

        // Reload button — triggers DataTables to re-fetch data
        jQuery(document).on('click', '.dt-reload-btn', function () {
            var tableId = jQuery(this).data('table');
            var $banner = jQuery(this).closest('.alert');
            if (tableId && jQuery.fn.DataTable.isDataTable('#' + tableId)) {
                jQuery('#' + tableId).DataTable().ajax.reload(null, false);
            }
            $banner.remove();
        });
    }

    setupDtErrorHandler();
    document.addEventListener('DOMContentLoaded', setupDtErrorHandler);
})();
</script>

@include('sweetalert::alert')

@yield('third_party_scripts')

@stack('page_scripts')

@livewireScripts
