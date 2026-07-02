<table>
    <thead>
        <tr>
            <th>Product Category</th>
            <th>Product Name</th>
            <th>Product Code</th>
            <th>Comments</th>
            <th>Reorder Quantity</th>
            <th>Generated Date</th>
        </tr>
    </thead>
    <tbody>
        @forelse($products as $product)
            <tr>
                <td>{{ $product->category->category_name ?? '-' }}</td>
                <td>{{ $product->product_name }}</td>
                <td>{{ $product->product_code }}</td>
                <td>{{ $product->product_note ?? '-' }}</td>
                <td>
                    @if($product->product_quantity < $product->product_stock_alert)
                        {{ $product->product_stock_alert - $product->product_quantity }}
                    @else
                        -
                    @endif
                </td>
                <td>{{ $product->created_at->format('d-m-Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">No products found matching the criteria.</td>
            </tr>
        @endforelse
    </tbody>
</table>
