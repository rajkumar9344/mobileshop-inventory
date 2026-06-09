
<!-- Home -->
<li class="c-sidebar-nav-item {{ request()->routeIs('home') ? 'c-active' : '' }}">
    <a class="c-sidebar-nav-link" href="{{ route('home') }}">
        <i class="c-sidebar-nav-icon bi bi-house" style="line-height: 1;"></i> Home
    </a>
</li>

<!-- Rack Management -->
@can('access_racks')
<li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('rack-master.*') ? 'c-show' : '' }}">
    <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
        <i class="c-sidebar-nav-icon bi bi-box" style="line-height: 1;"></i> Rack Management
    </a>
    <ul class="c-sidebar-nav-dropdown-items">
        @can('create_racks')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('rack.create') ? 'c-active' : '' }}" href="{{ route('rack.create') }}">
                <i class="c-sidebar-nav-icon bi bi-plus-square" style="line-height: 1;"></i> Create Rack
            </a>
        </li>
        @endcan
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('rack.index') ? 'c-active' : '' }}" href="{{ route('rack.index') }}">
                <i class="c-sidebar-nav-icon bi bi-list-ul" style="line-height: 1;"></i> All Racks
            </a>
        </li>
        @can('print_rack_barcodes')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('rack.barcode.print') ? 'c-active' : '' }}" href="{{ route('rack.barcode.print') }}">
                <i class="c-sidebar-nav-icon bi bi-printer" style="line-height: 1;"></i> Print Barcode
            </a>
        </li>
        @endcan
    </ul>
</li>
@endcan

<!-- Bin Management -->
@can('access_bins')
<li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('bin.*') ? 'c-show' : '' }}">
    <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
        <i class="c-sidebar-nav-icon bi bi-archive" style="line-height: 1;"></i> Bin Management
    </a>
    <ul class="c-sidebar-nav-dropdown-items">
        @can('create_bins')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('bin.create') ? 'c-active' : '' }}" href="{{ route('bin.create') }}">
                <i class="c-sidebar-nav-icon bi bi-plus-square" style="line-height: 1;"></i> Create Bin
            </a>
        </li>
        @endcan
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('bin.index') ? 'c-active' : '' }}" href="{{ route('bin.index') }}">
                <i class="c-sidebar-nav-icon bi bi-list-ul" style="line-height: 1;"></i> All Bins
            </a>
        </li>
        @can('print_bin_barcodes')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('bin.barcode.print') ? 'c-active' : '' }}" href="{{ route('bin.barcode.print') }}">
                <i class="c-sidebar-nav-icon bi bi-printer" style="line-height: 1;"></i> Print Barcode
            </a>
        </li>
        @endcan
    </ul>
</li>
@endcan

<!-- Products -->
@can('access_products')
<li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('products.*') || request()->routeIs('product-categories.*') || request()->routeIs('product-subcategories.*') ? 'c-show' : '' }}">
    <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
        <i class="c-sidebar-nav-icon bi bi-journal-bookmark" style="line-height: 1;"></i> Products
    </a>
    <ul class="c-sidebar-nav-dropdown-items">
        @can('access_product_categories')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('product-categories.*') ? 'c-active' : '' }}" href="{{ route('product-categories.index') }}">
                <i class="c-sidebar-nav-icon bi bi-collection" style="line-height: 1;"></i> Brands
            </a>
        </li>
        @endcan
        @can('access_product_subcategories')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('product-subcategories.*') ? 'c-active' : '' }}" href="{{ route('product-subcategories.index') }}">
                <i class="c-sidebar-nav-icon bi bi-list" style="line-height: 1;"></i> Sub-category
            </a>
        </li>
        @endcan
        @can('create_products')
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('products.create') ? 'c-active' : '' }}" href="{{ route('products.create') }}">
                <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Product
            </a>
        </li>
        @endcan
        <li class="c-sidebar-nav-item">
            <a class="c-sidebar-nav-link {{ request()->routeIs('products.index') ? 'c-active' : '' }}" href="{{ route('products.index') }}">
                <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Products
            </a>
        </li>
        @can('print_product_barcodes')
           <li class="c-sidebar-nav-item">
               <a class="c-sidebar-nav-link {{ request()->routeIs('barcode.print') ? 'c-active' : '' }}" href="{{ route('barcode.print') }}">
                   <i class="c-sidebar-nav-icon bi bi-printer" style="line-height: 1;"></i> Print Barcode
               </a>
           </li>
        @endcan
    </ul>
</li>
@endcan

<!-- Customers -->
@can('access_customers')
    <li class="c-sidebar-nav-item {{ request()->routeIs('customers.*') ? 'c-active' : '' }}">
        <a class="c-sidebar-nav-link" href="{{ route('customers.index') }}">
            <i class="c-sidebar-nav-icon bi bi-people" style="line-height: 1;"></i> Customers
        </a>
    </li>
@endcan

<!-- Suppliers -->
@can('access_suppliers')
    <li class="c-sidebar-nav-item {{ request()->routeIs('suppliers.*') ? 'c-active' : '' }}">
        <a class="c-sidebar-nav-link" href="{{ route('suppliers.index') }}">
            <i class="c-sidebar-nav-icon bi bi-people" style="line-height: 1;"></i> Suppliers
        </a>
    </li>
@endcan

<!-- Sales -->
@can('access_sales')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('sales.*') || request()->routeIs('sale-payments*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-receipt" style="line-height: 1;"></i> Sales
        </a>
        @can('create_sales')
            <ul class="c-sidebar-nav-dropdown-items">
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('sales.create') ? 'c-active' : '' }}" href="{{ route('sales.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Sale
                    </a>
                </li>
            </ul>
        @endcan
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('sales.index') ? 'c-active' : '' }}" href="{{ route('sales.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Sales
                </a>
            </li>
            @if (Route::has('sales-receipts.index'))
                @can('access_sales_receipts')
                    <li class="c-sidebar-nav-item">
                        <a class="c-sidebar-nav-link {{ request()->routeIs('sales-receipts.*') ? 'c-active' : '' }}" href="{{ route('sales-receipts.index') }}">
                            <i class="c-sidebar-nav-icon bi bi-cash" style="line-height: 1;"></i> Sales Receipts
                        </a>
                    </li>
                @endcan
            @endif
        </ul>
    </li>
@endcan

<!-- Purchases -->
@can('access_purchases')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('purchases.*') || request()->routeIs('purchase-payments*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-bag" style="line-height: 1;"></i> Purchases
        </a>
        @can('create_purchase')
            <ul class="c-sidebar-nav-dropdown-items">
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('purchases.create') ? 'c-active' : '' }}" href="{{ route('purchases.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Purchase
                    </a>
                </li>
            </ul>
        @endcan
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('purchases.index') ? 'c-active' : '' }}" href="{{ route('purchases.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Purchases
                </a>
            </li>
            @if (Route::has('purchases-receipts.index'))
                @can('access_purchases_receipts')
                    <li class="c-sidebar-nav-item">
                        <a class="c-sidebar-nav-link {{ request()->routeIs('purchases-receipts.*') ? 'c-active' : '' }}" href="{{ route('purchases-receipts.index') }}">
                            <i class="c-sidebar-nav-icon bi bi-cash" style="line-height: 1;"></i> Purchases Receipts
                        </a>
                    </li>
                @endcan
            @endif
        </ul>
    </li>
@endcan

<!-- Sale Returns -->
@can('access_sale_returns')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('sale-returns.*') || request()->routeIs('sale-return-payments.*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-arrow-return-left" style="line-height: 1;"></i> Sale Returns
        </a>
        @can('create_sale_returns')
            <ul class="c-sidebar-nav-dropdown-items">
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('sale-returns.create') ? 'c-active' : '' }}" href="{{ route('sale-returns.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Sale Return
                    </a>
                </li>
            </ul>
        @endcan
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('sale-returns.index') ? 'c-active' : '' }}" href="{{ route('sale-returns.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Sale Returns
                </a>
            </li>
        </ul>
    </li>
@endcan

<!-- Purchase Returns -->
@can('access_purchase_returns')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('purchase-returns.*') || request()->routeIs('purchase-return-payments.*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-arrow-return-right" style="line-height: 1;"></i> Purchase Returns
        </a>
        @can('create_purchase_returns')
            <ul class="c-sidebar-nav-dropdown-items">
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('purchase-returns.create') ? 'c-active' : '' }}" href="{{ route('purchase-returns.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Purchase Return
                    </a>
                </li>
            </ul>
        @endcan
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('purchase-returns.index') ? 'c-active' : '' }}" href="{{ route('purchase-returns.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Purchase Returns
                </a>
            </li>
        </ul>
    </li>
@endcan

<!-- Stock Adjustments -->
@can('access_adjustments')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('adjustments.*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-clipboard-check" style="line-height: 1;"></i> Stock Adjustments
        </a>
        <ul class="c-sidebar-nav-dropdown-items">
            @can('create_adjustments')
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('adjustments.create') ? 'c-active' : '' }}" href="{{ route('adjustments.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Adjustment
                    </a>
                </li>
            @endcan
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('adjustments.index') ? 'c-active' : '' }}" href="{{ route('adjustments.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Adjustments
                </a>
            </li>
        </ul>
    </li>
@endcan

<!-- Quotations -->
@can('access_quotations')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('quotations.*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-cart-check" style="line-height: 1;"></i> Quotations
        </a>
        <ul class="c-sidebar-nav-dropdown-items">
            @can('create_quotations')
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('quotations.create') ? 'c-active' : '' }}" href="{{ route('quotations.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Quotation
                    </a>
                </li>
            @endcan
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('quotations.index') ? 'c-active' : '' }}" href="{{ route('quotations.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Quotations
                </a>
            </li>
        </ul>
    </li>
@endcan

<!-- Expenses -->
@can('access_expenses')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-wallet2" style="line-height: 1;"></i> Expenses
        </a>
        <ul class="c-sidebar-nav-dropdown-items">
            @can('access_expense_categories')
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('expense-categories.*') ? 'c-active' : '' }}" href="{{ route('expense-categories.index') }}">
                        <i class="c-sidebar-nav-icon bi bi-collection" style="line-height: 1;"></i> Categories
                    </a>
                </li>
            @endcan
            @can('create_expenses')
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('expenses.create') ? 'c-active' : '' }}" href="{{ route('expenses.create') }}">
                        <i class="c-sidebar-nav-icon bi bi-journal-plus" style="line-height: 1;"></i> Create Expense
                    </a>
                </li>
            @endcan
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('expenses.index') ? 'c-active' : '' }}" href="{{ route('expenses.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-journals" style="line-height: 1;"></i> All Expenses
                </a>
            </li>
        </ul>
    </li>
@endcan

<!-- Reports -->
@can('access_reports')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('*-report.index') || request()->routeIs('reports.*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-graph-up" style="line-height: 1;"></i> Reports
        </a>
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('stock-report.index') ? 'c-active' : '' }}" href="{{ route('stock-report.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-clipboard-data" style="line-height: 1;"></i> Purchase Order Report
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('sales-outstanding-report.index') ? 'c-active' : '' }}" href="{{ route('sales-outstanding-report.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-exclamation-triangle" style="line-height: 1;"></i> Sales Outstanding Report
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('purchase-outstanding-report.index') ? 'c-active' : '' }}" href="{{ route('purchase-outstanding-report.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-exclamation-circle" style="line-height: 1;"></i> Purchase Outstanding Report
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('customers-payment-report.index') ? 'c-active' : '' }}" href="{{ route('customers-payment-report.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-cash" style="line-height: 1;"></i> Customers Payment Report
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('gstr-report.index') ? 'c-active' : '' }}" href="{{ route('gstr-report.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-file-earmark-text" style="line-height: 1;"></i> GSTR Report
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('daily-operations-report.index') ? 'c-active' : '' }}" href="{{ route('daily-operations-report.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-calendar-check" style="line-height: 1;"></i> Daily Operations Report
                </a>
            </li>
            <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('reports.ledger') || request()->routeIs('reports.supplier-ledger') ? 'c-show' : '' }}">
                <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
                    <i class="c-sidebar-nav-icon bi bi-book" style="line-height: 1;"></i> Ledger Report
                </a>
                <ul class="c-sidebar-nav-dropdown-items">
                    <li class="c-sidebar-nav-item">
                        <a class="c-sidebar-nav-link {{ request()->routeIs('reports.ledger') ? 'c-active' : '' }}" href="{{ route('reports.ledger') }}">
                            <i class="c-sidebar-nav-icon bi bi-file-earmark-text" style="line-height: 1;"></i> Customer Ledger Report
                        </a>
                    </li>
                    <li class="c-sidebar-nav-item">
                        <a class="c-sidebar-nav-link {{ request()->routeIs('reports.supplier-ledger') ? 'c-active' : '' }}" href="{{ route('reports.supplier-ledger') }}">
                            <i class="c-sidebar-nav-icon bi bi-journal-text" style="line-height: 1;"></i> Supplier Ledger Report
                        </a>
                    </li>
                </ul>
            </li>
        
        </ul>
    </li>
@endcan

<!-- User Management -->
@can('access_user_management')
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('roles*') || request()->routeIs('users*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-people" style="line-height: 1;"></i> User Management
        </a>
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('users.create') ? 'c-active' : '' }}" href="{{ route('users.create') }}">
                    <i class="c-sidebar-nav-icon bi bi-person-plus" style="line-height: 1;"></i> Create User
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ (request()->routeIs('users*') && !request()->routeIs('users.create')) ? 'c-active' : '' }}" href="{{ route('users.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-person-lines-fill" style="line-height: 1;"></i> All Users
                </a>
            </li>
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('roles*') ? 'c-active' : '' }}" href="{{ route('roles.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-key" style="line-height: 1;"></i> Roles & Permissions
                </a>
            </li>
        </ul>
    </li>
@endcan

<!-- Settings -->
@canany(['access_currencies', 'access_settings', 'access_units'])
    <li class="c-sidebar-nav-item c-sidebar-nav-dropdown {{ request()->routeIs('currencies*') || request()->routeIs('units*') ? 'c-show' : '' }}">
        <a class="c-sidebar-nav-link c-sidebar-nav-dropdown-toggle" href="#">
            <i class="c-sidebar-nav-icon bi bi-gear" style="line-height: 1;"></i> Settings
        </a>
        @can('access_units')
            <ul class="c-sidebar-nav-dropdown-items">
                <li class="c-sidebar-nav-item">
                    <a class="c-sidebar-nav-link {{ request()->routeIs('units*') ? 'c-active' : '' }}" href="{{ route('units.index') }}">
                        <i class="c-sidebar-nav-icon bi bi-calculator" style="line-height: 1;"></i> Units
                    </a>
                </li>
            </ul>
        @endcan
        @can('access_currencies')
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('currencies*') ? 'c-active' : '' }}" href="{{ route('currencies.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-cash-stack" style="line-height: 1;"></i> Currencies
                </a>
            </li>
        </ul>
        @endcan
        @can('access_settings')
        <ul class="c-sidebar-nav-dropdown-items">
            <li class="c-sidebar-nav-item">
                <a class="c-sidebar-nav-link {{ request()->routeIs('settings*') ? 'c-active' : '' }}" href="{{ route('settings.index') }}">
                    <i class="c-sidebar-nav-icon bi bi-sliders" style="line-height: 1;"></i> System Settings
                </a>
            </li>
        </ul>
        @endcan
    </li>
@endcanany
