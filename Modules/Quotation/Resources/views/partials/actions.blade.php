<div class="btn-group dropleft">
    <button type="button" class="btn btn-ghost-primary dropdown rounded" data-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
    @php
        $isDraft      = isset($data->status) && $data->status === 'Draft';
        $emailAddress = $data->customer?->customer_email ?? $data->contact_email ?? null;
        $lastEmail    = optional($data->lastEmailLog);
    @endphp
    <div class="dropdown-menu">
        @can('edit_quotations')
            <a href="{{ route('quotations.edit', $data->id) }}" class="dropdown-item">
                <i class="bi bi-pencil mr-2 text-primary" style="line-height: 1;"></i> Edit
            </a>
        @endcan
        @can('show_quotations')
            <a href="{{ route('quotations.show', $data->id) }}" class="dropdown-item">
                <i class="bi bi-eye mr-2 text-info" style="line-height: 1;"></i> View
            </a>
            @if(!$isDraft)
                <a target="_blank" href="{{ route('quotations.pdf', $data->id) }}" class="dropdown-item">
                    <i class="bi bi-receipt mr-2 text-info" style="line-height: 1;"></i> Quotation (PDF)
                </a>
            @endif
        @endcan
        @can('send_quotation_mails')
            @if(!$isDraft)
                @if($emailAddress)
                    @if($lastEmail && $lastEmail->id)
                        @php
                            $badgeClass = $lastEmail->status === 'sent' ? 'badge-success' : ($lastEmail->status === 'failed' ? 'badge-danger' : 'badge-secondary');
                            try {
                                $time = $lastEmail->sent_at
                                    ? \Illuminate\Support\Carbon::parse($lastEmail->sent_at)->diffForHumans()
                                    : \Illuminate\Support\Carbon::parse($lastEmail->created_at)->diffForHumans();
                            } catch (\Throwable $e) {
                                $time = $lastEmail->sent_at ?: ($lastEmail->created_at ?: '');
                            }
                        @endphp
                        <div class="dropdown-item">
                            <span class="badge {{ $badgeClass }}" style="margin-right:8px;">{{ ucfirst($lastEmail->status) }}</span>
                            <small class="text-muted">{{ $time }}</small>
                        </div>
                    @endif
                    <button class="dropdown-item" onclick="event.preventDefault(); document.getElementById('sendEmail{{ $data->id }}').submit()">
                        <i class="bi bi-envelope mr-2 text-success" style="line-height: 1;"></i> Send Email
                        <form id="sendEmail{{ $data->id }}" class="d-none" action="{{ route('quotations.send-email', $data->id) }}" method="POST">
                            @csrf
                        </form>
                    </button>
                @else
                    <button class="dropdown-item" onclick="event.preventDefault(); alert('Mail is not available for this Customer.')">
                        <i class="bi bi-envelope mr-2 text-success" style="line-height: 1;"></i> Send Email
                    </button>
                @endif
            @endif
        @endcan
        @can('delete_quotations')
            <button class="dropdown-item text-danger" onclick="
                event.preventDefault();
                if (confirm('Are you sure? It will delete the data permanently!')) {
                    document.getElementById('destroy{{ $data->id }}').submit();
                }">
                <i class="bi bi-trash mr-2 text-danger" style="line-height: 1;"></i> Delete
                <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('quotations.destroy', $data->id) }}" method="POST">
                    @csrf
                    @method('delete')
                </form>
            </button>
        @endcan
    </div>
</div>
