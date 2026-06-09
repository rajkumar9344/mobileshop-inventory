@if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('show_bins'))
    <a href="{{ route('bin.show', $data->id) }}" class="btn btn-sm btn-info">
        <i class="bi bi-eye"></i>
    </a>
@endif
@if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('edit_bins'))
    <a href="{{ route('bin.edit', $data->id) }}" class="btn btn-sm btn-warning">
        <i class="bi bi-pencil"></i>
    </a>
@endif
@if(auth()->user()->hasRole('Super Admin') || auth()->user()->can('delete_bins'))
    <form action="{{ route('bin.destroy', $data->id) }}" method="POST" class="d-inline">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this bin?')">
            <i class="bi bi-trash"></i>
        </button>
    </form>
@endif