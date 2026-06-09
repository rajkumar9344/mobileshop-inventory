<span class="badge {{ $data->status == 'active' ? 'badge-success' : 'badge-secondary' }}">
    {{ ucfirst($data->status) }}
</span>