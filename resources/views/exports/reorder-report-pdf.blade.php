<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order Report - {{ now()->format('d-m-Y') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .filters {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-center {
            text-align: center;
        }
        .no-data {
            text-align: center;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Purchase Order Report</h1>
        <p>Generated on: {{ now()->format('d-m-Y h:i:s A') }}</p>
    </div>

    @if(isset($filters) && (!empty($filters['category_id']) || !empty($filters['supplier_id']) || !empty($filters['compatibility']) || !empty($filters['generated_date_from']) || !empty($filters['generated_date_to']) || !empty($filters['search'])))
    <div class="filters">
        <h4>Applied Filters:</h4>
        @if(!empty($filters['category_id']))
            <p><strong>Category:</strong> {{ \Modules\Product\Entities\Category::find($filters['category_id'])->category_name ?? 'N/A' }}</p>
        @endif
        @if(!empty($filters['supplier_id']))
            <p><strong>Shop Name (Supplier):</strong> {{ \Modules\People\Entities\Supplier::find($filters['supplier_id'])->supplier_name ?? 'N/A' }}</p>
        @endif
        @if(!empty($filters['compatibility']))
            <p><strong>Compatibility:</strong> {{ $filters['compatibility'] }}</p>
        @endif
        @if(!empty($filters['generated_date_from']) || !empty($filters['generated_date_to']))
            <p><strong>Date Range:</strong>
                @if(!empty($filters['generated_date_from'])) From: {{ date('d-m-Y', strtotime($filters['generated_date_from'])) }} @endif
                @if(!empty($filters['generated_date_to'])) To: {{ date('d-m-Y', strtotime($filters['generated_date_to'])) }} @endif
            </p>
        @endif
        @if(!empty($filters['search']))
            <p><strong>Search:</strong> {{ $filters['search'] }}</p>
        @endif
    </div>
    @endif

    <table>
        <thead>
                <tr>
                <th>Product Category</th>
                <th>Product Name</th>
                <th>Product Code</th>
                <th>Compatibility</th>
                <th>Shop Name (Supplier)</th>
                <th class="text-center">Reorder Quantity</th>
                <th class="text-center">Generated Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->category->category_name ?? '-' }}</td>
                    <td>{{ $product->product_name }}</td>
                    <td>{{ $product->product_code }}</td>
                    <td>{{ $product->product_note ?? '-' }}</td>
                    <td>{{ $product->supplier->supplier_name ?? '-' }}</td>
                    <td class="text-center">
                        @if($product->product_quantity < $product->product_stock_alert)
                            {{ $product->product_stock_alert - $product->product_quantity }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">{{ $product->created_at->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="no-data">No products found matching the criteria.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 20px; text-align: center; font-size: 10px; color: #666;">
        <p>Total Products: {{ $products->count() }}</p>
    </div>
</body>
</html>
