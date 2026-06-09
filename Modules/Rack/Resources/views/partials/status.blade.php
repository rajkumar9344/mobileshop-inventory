@if ($data->status == 'Active')
    <span class="badge badge-success">
        {{ $data->status }}
    </span>
@else
    <span class="badge badge-secondary">
        {{ $data->status }}
    </span>
@endif