@extends('layouts.app')

@section('title', 'Products')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        /* Ensure the datatable controls remain fixed and only the table body scrolls horizontally */
        .product-table-container {
            width: 100%;
        }
        /* Reusable horizontal scroll container for wide tables */
        .product-horizontal-scroll {
            overflow-x: auto;
            overflow-y: visible;
        }
        .product-horizontal-scroll::-webkit-scrollbar {
            height: 10px;
        }
        .product-horizontal-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }
        .product-horizontal-scroll::-webkit-scrollbar-thumb {
            background: #6c757d; /* bootstrap secondary gray */
            border-radius: 6px;
            border: 2px solid #f1f1f1;
        }
        .product-horizontal-scroll {
            scrollbar-width: thin;
            scrollbar-color: #6c757d #f1f1f1;
        }
        /* Ensure daterangepicker overlays the layout (sidebar/header) */
        .daterangepicker {
            z-index: 3000 !important;
        }

        /* Product filter panel spacing tweaks (view-scoped) */
        .filter-panel {
            gap: 1rem !important; /* slightly larger gap between items */
        }

        .filter-panel .form-group {
            margin-bottom: 0; /* keep inline alignment */
        }

        .filter-panel .input-group {
            min-width: 280px;
        }

        .filter-panel .btn-group {
            gap: 0.4rem;
        }

        /* Product page: hide component Apply/Clear buttons and make daterange compact */
        .daterange-compact .filter-panel { padding: 0.25rem 0.5rem; }
        .daterange-compact .filter-panel .input-group { max-width: none !important; width: 100% !important; }
        .daterange-compact #apply-filters-product-table,
        .daterange-compact #clear-filters-product-table,
        .daterange-compact .filter-shortcuts { display: none !important; }
        .daterange-compact .btn { padding: .25rem .4rem; font-size: .85rem; }
        .daterange-compact .btn-sm { padding: .2rem .35rem; font-size: .8rem; }
    </style>
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Products</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        @can('create_products')
                            <a href="{{ route('products.create') }}" class="btn btn-primary">
                                Add Product <i class="bi bi-plus"></i>
                            </a>
                        @endcan

                        <hr>

                        <div class="product-table-container">
                            <!-- Product-specific filter panel with Category, Subcategory, and Date Range -->
                            <div class="p-3 bg-light rounded filter-panel d-flex flex-wrap align-items-center gap-3 mb-3">
                                <!-- Category Filter -->
                                <div class="form-group mb-0" style="min-width: 180px;">
                                    <select id="filter-category" class="form-control form-control-sm">
                                        <option value="">All Categories</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Subcategory Filter -->
                                <div class="form-group mb-0" style="min-width: 180px;">
                                    <select id="filter-subcategory" class="form-control form-control-sm">
                                        <option value="">All Subcategories</option>
                                        @foreach($subcategories as $subcategory)
                                            <option value="{{ $subcategory->id }}" data-category="{{ $subcategory->category_id }}">{{ $subcategory->subcategory_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Date Range Filter (product-specific: compact + Reset) -->
                                <div class="d-flex align-items-center daterange-compact" style="min-width: 320px;">
                                    @include('components.daterange-filter', ['tableId' => 'product-table', 'noWrapper' => true])
                                    <button id="reset-filters-product-table" type="button" class="btn btn-outline-danger btn-sm ml-2" style="white-space: nowrap;"><i class="bi bi-arrow-clockwise"></i> Reset</button>
                                </div>
                            </div>

                            <div class="w-100 mb-2">
                                <small class="text-muted">Quick: select a preset or pick custom range</small>
                            </div>

                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
    <script>
        $(document).ready(function () {
            var table = $('#product-table').DataTable();

            // Initialize daterangepicker (scoped to product-table instance)
            $('#filter-daterange-product-table').daterangepicker({
                autoUpdateInput: false,
                autoApply: true,
                opens: 'right',
                drops: 'down',
                showDropdowns: true,
                linkedCalendars: false,
                alwaysShowCalendars: true,
                showCustomRangeLabel: false,
                locale: { 
                    cancelLabel: 'Clear',
                    applyLabel: 'Apply',
                    format: 'DD/MM/YYYY'
                },
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, function(start, end, label) {
                $('#filter-start-product-table').val(start.format('YYYY-MM-DD'));
                $('#filter-end-product-table').val(end.format('YYYY-MM-DD'));
                $('#filter-daterange-product-table').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
            });

            // Handle daterangepicker apply event (auto-apply: reload table)
            $('#filter-daterange-product-table').on('apply.daterangepicker', function(ev, picker) {
                $('#filter-start-product-table').val(picker.startDate.format('YYYY-MM-DD'));
                $('#filter-end-product-table').val(picker.endDate.format('YYYY-MM-DD'));
                $('#filter-daterange-product-table').val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
                // Auto-reload table on date apply
                if ($.fn.dataTable.isDataTable('#product-table')) {
                    $('#product-table').DataTable().ajax.reload();
                }
            });

            // Handle daterangepicker cancel event
            $('#filter-daterange-product-table').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                $('#filter-start-product-table').val('');
                $('#filter-end-product-table').val('');
            });

            // Store all subcategory options (full list with all category_ids) for client-side filtering
            var allSubcategoryOptions = $('#filter-subcategory option').clone();

            // On page load: show deduplicated subcategory list (unique by name)
            // We keep a flag `subcategoryDeduped` so server-side filtering can
            // know whether the select options represent unique-names (deduped)
            var subcategoryDeduped = false;
            (function() {
                var $sub = $('#filter-subcategory');
                $sub.html('<option value="">All Subcategories</option>');
                var seenNames = {};
                allSubcategoryOptions.each(function() {
                    var $opt = $(this);
                    if ($opt.val() === '') return;
                    var name = $opt.text().trim();
                    if (!seenNames[name]) {
                        seenNames[name] = true;
                        $sub.append($opt.clone());
                    }
                });
                subcategoryDeduped = true;
            })();

            // Category change - filter subcategories client-side and auto-reload table
            $('#filter-category').on('change', function() {
                var categoryId = $(this).val();
                var $subcategorySelect = $('#filter-subcategory');
                
                // Reset subcategory selection
                $subcategorySelect.val('');
                
                // Reset to "All Subcategories" option first
                $subcategorySelect.html('<option value="">All Subcategories</option>');
                
                if (categoryId) {
                    // Filter and show only subcategories for the selected category
                    allSubcategoryOptions.each(function() {
                        var $opt = $(this);
                        if ($opt.val() !== '' && $opt.data('category') == categoryId) {
                            $subcategorySelect.append($opt.clone());
                        }
                    });
                } else {
                    // Show all subcategories when no category is selected — deduplicate by name
                    var seenNames = {};
                    allSubcategoryOptions.each(function() {
                        var $opt = $(this);
                        if ($opt.val() === '') return; // skip the "All" placeholder
                        var name = $opt.text().trim();
                        if (!seenNames[name]) {
                            seenNames[name] = true;
                            $subcategorySelect.append($opt.clone());
                        }
                    });
                }
                
                // Auto-reload table on category change
                if ($.fn.dataTable.isDataTable('#product-table')) {
                    $('#product-table').DataTable().ajax.reload();
                }
            });

            // Subcategory change - auto-reload table
            $('#filter-subcategory').on('change', function() {
                if ($.fn.dataTable.isDataTable('#product-table')) {
                    $('#product-table').DataTable().ajax.reload();
                }
            });

            // The daterange component handles Apply/Clear for the date filters (scoped to product-table).

            // Add filter parameters to DataTable AJAX request
            table.on('preXhr.dt', function(e, settings, data) {
                data.category_id = $('#filter-category').val() || null;
                var subVal = $('#filter-subcategory').val() || null;
                if (subcategoryDeduped && subVal) {
                    // When deduped, the select holds one representative id but the
                    // name is the meaningful filter across categories. Send the
                    // subcategory_name to the server and omit subcategory_id.
                    data.subcategory_name = $('#filter-subcategory option:selected').text().trim() || null;
                    data.subcategory_id = null;
                } else {
                    data.subcategory_id = subVal;
                    data.subcategory_name = null;
                }
                data.start_date = $('#filter-start-product-table').val() || null;
                data.end_date = $('#filter-end-product-table').val() || null;
            });

            table.on('draw.dt', function () {
                table.columns.adjust();
            });

            // Also adjust on window resize to handle layout changes
            $(window).on('resize', function () {
                table.columns.adjust();
            });

            // Product-specific: Reset filters button clears all `filter-*` controls and reloads table
            $('#reset-filters-product-table').on('click', function(e){
                e.preventDefault();
                var $panel = $('#filter-daterange-product-table').closest('.filter-panel');
                if (!$panel || !$panel.length) return;

                // clear all filter-* controls in this panel
                $panel.find('input[id^="filter-"], select[id^="filter-"], textarea[id^="filter-"]')
                    .each(function(){
                        if ($(this).is('select')) {
                            $(this).val('').trigger('change');
                        } else if ($(this).is(':checkbox') || $(this).is(':radio')) {
                            $(this).prop('checked', false);
                        } else {
                            $(this).val('');
                        }
                    });

                // restore subcategory options (deduplicated by name) and reset selection
                if (typeof allSubcategoryOptions !== 'undefined') {
                    var $sub = $('#filter-subcategory');
                    $sub.html('<option value="">All Subcategories</option>');
                    var seenNames = {};
                    allSubcategoryOptions.each(function() {
                        var $opt = $(this);
                        if ($opt.val() === '') return;
                        var name = $opt.text().trim();
                        if (!seenNames[name]) {
                            seenNames[name] = true;
                            $sub.append($opt.clone());
                        }
                    });
                } else {
                    $('#filter-subcategory').val('');
                }

                // clear DataTable global search and reload
                try { table.search(''); table.columns().search(''); } catch (err) {}
                if ($.fn.dataTable.isDataTable('#product-table')) {
                    $('#product-table').DataTable().ajax.reload();
                }
            });
        });
    </script>
@endpush
