@can('show_product_categories')
<a href="{{ route('product-categories.show', $data->id) }}" class="btn btn-info btn-sm" title="View">
    <i class="bi bi-eye"></i>
</a>
@endcan
@can('edit_product_categories')
<a href="{{ route('product-categories.edit', $data->id) }}" class="btn btn-info btn-sm">
    <i class="bi bi-pencil"></i>
</a>
@endcan
@can('delete_product_categories')
<button id="deactivate" class="btn btn-warning btn-sm" onclick="
    event.preventDefault();
    if (confirm('Are you sure? This will mark the Brand as inactive.')) {
        document.getElementById('destroy-category-{{ $data->id }}').submit();
    }
    ">
    <i class="bi bi-slash-circle"></i>
    <form id="destroy-category-{{ $data->id }}" class="d-none" action="{{ route('product-categories.destroy', $data->id) }}" method="POST">
        @csrf
        @method('delete')
    </form>
</button>
@endcan
