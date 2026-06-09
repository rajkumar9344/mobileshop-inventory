<div class="modal fade" id="binCreateModal" tabindex="-1" role="dialog" aria-labelledby="binCreateModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="binCreateModalLabel">Create Bin</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('bin.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="bin_rack_id">Rack Name/Number <span class="text-danger">*</span></label>
                        <select class="form-control" id="bin_rack_id" name="rack_id" required>
                            <option value="" selected disabled>Select Rack</option>
                            @foreach(\Modules\Rack\Entities\Rack::where('status', true)->get() as $rack)
                                <option value="{{ $rack->id }}">{{ $rack->rack_name }} ({{ $rack->rack_id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bin_id_input">Bin ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bin_id_input" name="bin_id" placeholder="B001" required maxlength="20" pattern="[A-Za-z0-9 _\-]+" title="Only letters, numbers, spaces, hyphens and underscore allowed" oninput="this.value = this.value.replace(/[^A-Za-z0-9_\- ]/g,'').slice(0,20)">
                    </div>

                    <div class="form-group">
                        <label for="bin_name_input">Bin Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="bin_name_input" name="bin_name" placeholder="Bin A" required maxlength="100" title="Max 100 characters" oninput="this.value = this.value.slice(0,100)">
                    </div>

                    <div class="form-group">
                        <label for="capacity_input">Bin Capacity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="capacity_input" name="capacity" placeholder="Max items/weight" required min="0" max="9999" title="Max 4 digits" oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,4)">
                    </div>

                    <div class="form-group">
                        <label for="bin_status_input">Status <span class="text-danger">*</span></label>
                        <select class="form-control" id="bin_status_input" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bin_barcode_input">Barcode</label>
                        <input type="text" class="form-control" id="bin_barcode_input" name="barcode" placeholder="Barcode" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Create <i class="bi bi-check"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if($errors->has('bin_id') || $errors->has('bin_name') || $errors->has('rack_id'))
            $('#binCreateModal').modal('show');
        @endif

        $('#binCreateModal form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').remove();

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                success: function(response) {
                    if (response && (response.id || response.bin_id)) {
                        var id = response.id || response.bin_id;
                        var text = response.bin_name || response.bin_id || response.name || $form.find('[name="bin_name"]').val();
                        if ($('#bin_no').length) {
                            $('#bin_no').append(new Option(text, text, true, true)).trigger('change');
                        }
                        $('#binCreateModal').modal('hide');
                        $form[0].reset();
                        // cleanup any leftover backdrop and restore page interaction
                        setTimeout(function() {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                        }, 200);
                    } else {
                        window.location.reload();
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, msgs) {
                            var $el = $form.find('[name="' + key + '"]');
                            if ($el.length) {
                                $el.addClass('is-invalid');
                                $el.after('<div class="invalid-feedback">' + msgs[0] + '</div>');
                            }
                        });
                    } else {
                        alert('An error occurred while creating bin.');
                    }
                }
            });
        });
    });
</script>
