<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Report</title>
    @include('reports.partials._print-styles')
    <style>
        table { table-layout: fixed; }
        colgroup col.col-no   { width: 4%; }
        colgroup col.col-cat  { width: 15%; }
        colgroup col.col-prod { width: 26%; }
        colgroup col.col-code { width: 12%; }
        colgroup col.col-comp { width: 20%; }
        colgroup col.col-reor { width: 11%; }
        colgroup col.col-date { width: 12%; }
    </style>
</head>
<body>

    <div class="action-bar">
        <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
        <button class="btn-close2" onclick="window.close()">&#x2715;&nbsp; Close</button>
        <span class="print-tip" style="font-size:11px;color:#888;margin-left:12px;">&#9432; In the print dialog, uncheck <strong>"Headers and footers"</strong> to hide the URL.</span>
        <span class="record-count">Total Records: <strong>{{ $products->count() }}</strong></span>
    </div>

    <div class="report-header">
        <h2>Purchase Order Report</h2>
        <p class="meta">Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>

    @php
        $hasFilters = !empty($filters['category_id']) || !empty($filters['compatibility']) || !empty($filters['generated_date_from']) || !empty($filters['generated_date_to']) || !empty($filters['search']);
    @endphp
    @if($hasFilters)
    <div class="filters-bar">
        <strong>Filters Applied &mdash;</strong>
        @if(!empty($filters['category_id']))
            <span>Category: <strong>{{ $filters['category_name'] ?? $filters['category_id'] }}</strong></span>
        @endif
        @if(!empty($filters['compatibility']))
            <span>Comments: <strong>{{ $filters['compatibility'] }}</strong></span>
        @endif
        @if(!empty($filters['generated_date_from']) || !empty($filters['generated_date_to']))
            <span>Date Range: <strong>{{ $filters['generated_date_from'] ?? '-' }} to {{ $filters['generated_date_to'] ?? '-' }}</strong></span>
        @endif
        @if(!empty($filters['search']))
            <span>Search: <strong>{{ $filters['search'] }}</strong></span>
        @endif
    </div>
    @endif

    <table>
        <colgroup>
            <col class="col-no">
            <col class="col-cat">
            <col class="col-prod">
            <col class="col-code">
            <col class="col-comp">
            <col class="col-reor">
            <col class="col-date">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Product Category</th>
                <th>Product Name</th>
                <th>Product Code</th>
                <th>Comments</th>
                <th>Reorder Qty</th>
                <th>Generated Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
            <tr>
                <td class="t-center">{{ $loop->iteration }}</td>
                <td class="t-left">{{ $product->category->category_name ?? '-' }}</td>
                <td class="t-left">{{ $product->product_name }}</td>
                <td class="t-center">{{ $product->product_code }}</td>
                <td class="t-left">{{ $product->product_note ?? '-' }}</td>
                <td class="t-center">
                    @if($product->product_quantity < $product->product_stock_alert)
                        {{ $product->product_stock_alert - $product->product_quantity }}
                    @else
                        -
                    @endif
                </td>
                <td class="t-center">{{ $product->created_at->format('d-m-Y') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="t-center" style="padding:20px;color:#888;">No products found matching the criteria.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="report-footer">
        Purchase Order Report &bull; Printed on {{ date('d-m-Y H:i:s') }} &bull; Total {{ $products->count() }} record(s)
    </div>

</body>
</html>
