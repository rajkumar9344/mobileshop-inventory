<div class="modal fade" id="rackCreateModal" tabindex="-1" role="dialog" aria-labelledby="rackCreateModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rackCreateModalLabel">Create Rack</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('rack.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rack_id_input">Rack ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rack_id_input" name="rack_id" placeholder="R001" required maxlength="20" pattern="[A-Za-z0-9 _\-]+" title="Only letters, numbers, spaces, hyphens and underscore allowed" oninput="this.value = this.value.replace(/[^A-Za-z0-9_\- ]/g,'').slice(0,20)">
                    </div>
                    <div class="form-group">
                        <label for="rack_name_input">Rack Name/Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rack_name_input" name="rack_name" placeholder="Rack A or Rack 5" required maxlength="100" title="Max 100 characters" oninput="this.value = this.value.slice(0,100)">
                    </div>
                    <div class="form-group">
                        <label for="rack_barcode_input">Barcode</label>
                        <input type="text" class="form-control" id="rack_barcode_input" name="barcode" placeholder="Barcode" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="rack_status_input">Status <span class="text-danger">*</span></label>
                        <select class="form-control" id="rack_status_input" name="status" required>
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
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
        @if($errors->has('rack_id') || $errors->has('rack_name'))
            $('#rackCreateModal').modal('show');
        @endif

        $('#rackCreateModal form').on('submit', function(e) {
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
                    if (response && (response.id || response.rack_id)) {
                        var id = response.id; // DB id
                        var rackId = response.rack_id; // rack code (R001 etc)
                        var rackName = response.rack_name || $form.find('[name="rack_name"]').val();

                        // Append to product page rack select(s) which use rack_id as value
                        $('select[name="rack_no"]').each(function() {
                            var $sel = $(this);
                            if ($sel.find('option[value="' + rackId + '"]').length === 0) {
                                $sel.append(new Option(rackId, rackId));
                            }
                        });

                        // Append to bin modal / other selects which use DB id as value (name="rack_id")
                        $('select[name="rack_id"]').each(function() {
                            var $sel = $(this);
                            var display = rackName + ' (' + rackId + ')';
                            if ($sel.find('option[value="' + id + '"]').length === 0) {
                                $sel.append(new Option(display, id));
                            }
                        });

                        // Select the new rack in product select if present
                        if ($('#rack_no').length) {
                            $('#rack_no').val(rackId).trigger('change');
                        }
                        $('#rackCreateModal').modal('hide');
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
                        alert('An error occurred while creating rack.');
                    }
                }
            });
        });
    });
</script>
