<div class="modal fade" id="categoryCreateModal" tabindex="-1" role="dialog" aria-labelledby="categoryCreateModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryCreateModalLabel">Create Brand</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('product-categories.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_code">Brand Code <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="category_code" id="category_code" required maxlength="15" pattern="[A-Za-z0-9_-]+" title="Only letters, numbers, hyphen and underscore" value="{{ 'CA_' . str_pad(\Modules\Product\Entities\Category::max('id') + 1, 2, '0', STR_PAD_LEFT) }}">
                    </div>
                    <div class="form-group">
                        <label for="category_name">Brand Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="category_name" id="category_name_input" required maxlength="100">
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
                @if($errors->has('category_name') || $errors->has('category_code'))
                    $('#categoryCreateModal').modal('show');
                @endif

                $('#categoryCreateModal form').on('submit', function(e) {
                    e.preventDefault();
                    var $form = $(this);
                    // clear previous validation
                    $form.find('.is-invalid').removeClass('is-invalid');
                    $form.find('.invalid-feedback').remove();

                    $.ajax({
                        url: $form.attr('action'),
                        method: 'POST',
                        data: $form.serialize(),
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        success: function(response) {
                            if (response && (response.id || response.category_id)) {
                                var id = response.id || response.category_id;
                                var text = response.category_name || response.name || $form.find('[name="category_name"]').val();
                                // Append the new brand option to every brand select on the page
                                $('select[name="category_id"]').each(function() {
                                    var $sel = $(this);
                                    // Avoid duplicate
                                    if ($sel.find('option[value="' + id + '"]').length === 0) {
                                        $sel.append(new Option(text, id));
                                    }
                                });
                                // Ensure main product form's brand select (id=category_id) selects the new brand and triggers change
                                if ($('#category_id').length) {
                                    $('#category_id').val(id).trigger('change');
                                }
                                $('#categoryCreateModal').modal('hide');
                                $form[0].reset();
                                // reload the categories table if it exists
                                if ($('#categories-table').length) {
                                    $('#categories-table').DataTable().ajax.reload();
                                }
                                // cleanup any leftover backdrop and restore page interaction
                                setTimeout(function() {
                                    $('.modal-backdrop').remove();
                                    $('body').removeClass('modal-open');
                                }, 200);
                            } else {
                                // fallback: reload to pick up new values
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
                                // Surface the real server error so the cause is visible (permission, 500, etc.)
                                var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                                    ? (xhr.responseJSON.message || xhr.responseJSON.error)
                                    : ('Unable to create brand (HTTP ' + xhr.status + ').');
                                alert(msg);
                            }
                        }
                    });
                });
            });
        </script>
