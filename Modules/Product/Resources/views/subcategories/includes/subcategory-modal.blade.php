<div class="modal fade" id="subcategoryCreateModal" tabindex="-1" role="dialog" aria-labelledby="subcategoryCreateModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="subcategoryCreateModalLabel">Create Sub-category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('product-subcategories.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_category_id">Brand <span class="text-danger">*</span></label>
                        <select name="category_id" id="modal_category_id" class="form-control" required>
                            <option value="" selected disabled>Select Brand</option>
                            @php
                                $cats = \Modules\Product\Entities\Category::where('status', true)
                                    ->select('id','category_name')
                                    ->get()
                                    ->sortBy(function($c){ return $c->category_name; }, SORT_NATURAL|SORT_FLAG_CASE)
                                    ->values();
                            @endphp
                            @foreach($cats as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subcategory_name">Subcategory Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="subcategory_name" id="subcategory_name_input" required maxlength="100">
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
                @if($errors->has('subcategory_name') || $errors->has('category_id'))
                    $('#subcategoryCreateModal').modal('show');
                @endif

                $('#subcategoryCreateModal form').on('submit', function(e) {
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
                            if (response && (response.id || response.subcategory_id)) {
                                var id = response.id || response.subcategory_id;
                                var text = response.subcategory_name || response.name || $form.find('[name="subcategory_name"]').val();
                                // only append to product page subcategory select if the product's selected category matches the created one
                                var createdCat = $form.find('[name="category_id"]').val();
                                if ($('#subcategory_id').length && $('#category_id').length && $('#category_id').val() == createdCat) {
                                    $('#subcategory_id').append(new Option(text, id, true, true)).trigger('change');
                                }
                                $('#subcategoryCreateModal').modal('hide');
                                $form[0].reset();
                                // reload the subcategories table if it exists
                                if ($('#product_subcategories-table').length) {
                                    $('#product_subcategories-table').DataTable().ajax.reload();
                                }
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
                                alert('An error occurred while creating subcategory.');
                            }
                        }
                    });
                });
            });
        </script>
