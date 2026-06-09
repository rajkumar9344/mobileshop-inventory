@can('show_product_subcategories')
<a href="{{ route('product-subcategories.show', $data->id) }}" class="btn btn-info btn-sm" title="View">
    <i class="bi bi-eye"></i>
</a>
@endcan
@can('edit_product_subcategories')
<a href="{{ route('product-subcategories.edit', $data->id) }}" class="btn btn-info btn-sm">
    <i class="bi bi-pencil"></i>
</a>
@endcan
@can('delete_product_subcategories')
<button id="deactivate" class="btn btn-warning btn-sm" onclick="
    event.preventDefault();
    if (confirm('Are you sure? This will mark the Subcategory as inactive.')) {
        document.getElementById('destroy-subcategory-{{ $data->id }}').submit();
    }
    ">
    <i class="bi bi-slash-circle"></i>
    <form id="destroy-subcategory-{{ $data->id }}" class="d-none" action="{{ route('product-subcategories.destroy', $data->id) }}" method="POST">
        @csrf
        @method('delete')
    </form>
</button>
@endcan
