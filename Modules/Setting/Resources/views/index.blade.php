@extends('layouts.app')

@section('title', 'Edit Settings')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Settings</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                @include('utils.alerts')
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">General Settings</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('patch')
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="company_name">Company Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="company_name" value="{{ old('company_name', $settings->company_name) }}" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="company_name_ar">Company Name (Arabic) <small class="text-muted">(optional)</small></label>
                                        <input type="text" class="form-control" name="company_name_ar" id="company_name_ar" value="{{ old('company_name_ar', $settings->company_name_ar) }}" maxlength="255" placeholder="اسم الشركة بالعربية">
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="company_email">Company Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="company_email" id="company_email" value="{{ old('company_email', $settings->company_email) }}" required maxlength="50" oninput="validateEmail(this, 'company-email-error');">
                                        <small id="company-email-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="company_phone">Company Phone <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="company_phone" id="company_phone" value="{{ old('company_phone', $settings->company_phone) }}" required maxlength="15" pattern="\+?[0-9]{7,15}" title="Only numbers and + (UAE contact number, max 15)" oninput="validatePhone(this, 'company-phone-error');">
                                        <small id="company-phone-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="company_email_2">Company Email 2 <small class="text-muted">(optional)</small></label>
                                        <input type="email" class="form-control" name="company_email_2" id="company_email_2" value="{{ old('company_email_2', $settings->company_email_2) }}" maxlength="255" oninput="validateEmail(this, 'company-email2-error');">
                                        <small id="company-email2-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="company_phone_2">Company Phone 2 <small class="text-muted">(optional)</small></label>
                                        <input type="tel" class="form-control" name="company_phone_2" id="company_phone_2" value="{{ old('company_phone_2', $settings->company_phone_2) }}" maxlength="15" pattern="\+?[0-9]{7,15}" title="Only numbers and + (UAE contact number, max 15)" oninput="validatePhone(this, 'company-phone2-error');">
                                        <small id="company-phone2-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="default_currency_id">Default Currency <span class="text-danger">*</span></label>
                                        <select name="default_currency_id" id="default_currency_id" class="form-control" required>
                                            @foreach(\Modules\Currency\Entities\Currency::all() as $currency)
                                                <option {{ (old('default_currency_id', $settings->default_currency_id) == $currency->id) ? 'selected' : '' }} value="{{ $currency->id }}">{{ $currency->currency_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="default_currency_position">Default Currency Position <span class="text-danger">*</span></label>
                                        <select name="default_currency_position" id="default_currency_position" class="form-control" required>
                                            <option {{ (old('default_currency_position', $settings->default_currency_position) == 'prefix') ? 'selected' : '' }} value="prefix">Prefix</option>
                                            <option {{ (old('default_currency_position', $settings->default_currency_position) == 'suffix') ? 'selected' : '' }} value="suffix">Suffix</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="notification_email">Notification Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="notification_email" id="notification_email" value="{{ old('notification_email', $settings->notification_email) }}" required maxlength="50" oninput="validateEmail(this, 'notification-email-error');">
                                        <small id="notification-email-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="site_logo">Application Logo</label>
                                        <input type="file" class="form-control-file" name="site_logo" id="site_logo" accept="image/png,image/jpeg,image/jpg,image/webp">
                                        <small class="form-text text-muted">Upload PNG/JPG/WebP up to 4MB. Recommended: 600x180 or larger with transparent background. The system auto-optimizes logo clarity. Leave empty to keep current logo.</small>
                                        @if(!empty($settings->site_logo))
                                            <div class="mt-2">
                                                <img src="{{ $settings->site_logo }}" alt="Current Logo" style="max-height: 70px; max-width: 220px; border: 1px solid #dee2e6; border-radius: 4px; padding: 4px; background: #fff;">
                                            </div>
                                            <div class="custom-control custom-checkbox mt-2">
                                                <input type="checkbox" class="custom-control-input" id="remove_site_logo" name="remove_site_logo" value="1">
                                                <label class="custom-control-label" for="remove_site_logo">Remove current logo</label>
                                            </div>
                                        @endif
                                        @error('site_logo')
                                            <small class="text-danger d-block">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="company_gst">Company TRN (Tax Registration No.)</label>
                                        <input type="text" class="form-control" name="company_gst" id="company_gst" value="{{ old('company_gst', $settings->company_gst) }}" maxlength="15" placeholder="e.g. 100123456700003" oninput="validateTRN(this, 'company-trn-error');">
                                        <small id="company-trn-error" class="text-danger" style="display: none;"></small>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="company_address">Company Address <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="company_address" value="{{ old('company_address', $settings->company_address) }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="company_address_ar">Company Address (Arabic) <small class="text-muted">(optional)</small></label>
                                        <input type="text" class="form-control" name="company_address_ar" id="company_address_ar" value="{{ old('company_address_ar', $settings->company_address_ar) }}" maxlength="500" placeholder="عنوان الشركة بالعربية">
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Bank Details</h5>
                    </div>
                    <div class="card-body">
                        @include('setting::partials.bank-fields')

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Save Changes</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                @if (session()->has('settings_smtp_message'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <div class="alert-body">
                            <span>{{ session('settings_smtp_message') }}</span>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    </div>
                @endif
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Mail Settings</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('settings.smtp.update') }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="mail_mailer">MAIL_MAILER <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="mail_mailer" value="{{ config('mail.default') }}" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="mail_host">MAIL_HOST <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="mail_host" value="{{ config('mail.mailers.smtp.host') }}" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="mail_port">MAIL_PORT <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="mail_port" value="{{ config('mail.mailers.smtp.port') }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="mail_mailer">MAIL_MAILER</label>
                                        <input type="text" class="form-control" name="mail_mailer" value="{{ config('mail.default') }}">
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="mail_username">MAIL_USERNAME</label>
                                        <input type="text" class="form-control" name="mail_username" value="{{ config('mail.mailers.smtp.username') }}">
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="mail_password">MAIL_PASSWORD</label>
                                        <input type="password" class="form-control" name="mail_password" value="">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="mail_encryption">MAIL_ENCRYPTION</label>
                                        <input type="text" class="form-control" name="mail_encryption" value="{{ config('mail.mailers.smtp.encryption') }}">
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="form-group">
                                        <label for="mail_from_address">MAIL_FROM_ADDRESS</label>
                                        <input type="email" class="form-control" name="mail_from_address" value="{{ config('mail.from.address') }}">
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="form-group">
                                        <label for="mail_from_name">MAIL_FROM_NAME <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="mail_from_name" value="{{ config('mail.from.name') }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/validation.js') }}"></script>
    <script>
        (function(){
            function csrf() { return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); }

            document.addEventListener('click', function(e){
                var btn = e.target.closest('.remove-qr-btn');
                if (!btn) return;
                var which = btn.getAttribute('data-which');
                if (!which) return;
                if (!confirm('Remove this QR image?')) return;

                btn.disabled = true;
                btn.innerText = 'Removing...';

                fetch('{{ route('settings.qr.remove') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ which: which })
                }).then(function(res){ return res.json(); }).then(function(json){
                    if (json.ok) {
                        // remove the existing preview and show placeholder
                        var container = btn.closest('.qr-preview-container');
                        if (container) {
                            var existingPreview = container.querySelector('.existing-preview');
                            if (existingPreview) existingPreview.remove();
                            // Create and show the no-preview placeholder
                            var placeholder = document.createElement('div');
                            placeholder.className = 'no-preview';
                            placeholder.style.cssText = 'background: #f8f9fa; border-radius: 8px; padding: 20px 15px; border: 2px dashed #dee2e6;';
                            placeholder.innerHTML = '<i class="bi bi-image" style="font-size: 24px; color: #adb5bd;"></i><div style="font-size: 11px; color: #adb5bd; margin-top: 4px;">No QR</div>';
                            container.insertBefore(placeholder, container.firstChild);
                            // Show new-preview area if present
                            var newPreview = container.querySelector('.new-preview');
                            if (newPreview) newPreview.style.display = 'none';
                        }
                    } else {
                        alert(json.message || 'Failed to remove');
                        btn.disabled = false;
                        btn.innerText = 'Remove';
                    }
                }).catch(function(){
                    alert('Failed to remove QR image');
                    btn.disabled = false;
                    btn.innerText = 'Remove';
                });
            });

            // File input change -> preview with validation
            function handleFileInput(inputId) {
                var input = document.querySelector(inputId);
                if (!input) return;

                var maxSize = 4 * 1024 * 1024; // 4MB
                var allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                input.addEventListener('change', function(){
                    var file = this.files && this.files[0];
                    var cardBody = this.closest('.card-body') || this.closest('.form-group');
                    if (!cardBody) return;
                    var container = cardBody.querySelector('.qr-preview-container');
                    if (!container) return;
                    var noPreview = container.querySelector('.no-preview');
                    var newPreview = container.querySelector('.new-preview');
                    var img = container.querySelector('.new-preview .qr-preview-img');

                    // Validate file
                    if (file) {
                        if (file.size > maxSize) {
                            alert('File size must be less than 4MB');
                            this.value = '';
                            return;
                        }
                        if (!allowedTypes.includes(file.type)) {
                            alert('Only JPG, PNG, and WebP images are allowed');
                            this.value = '';
                            return;
                        }
                    }

                    if (file && img) {
                        var reader = new FileReader();
                        reader.onload = function(e){
                            img.src = e.target.result;
                            if (noPreview) noPreview.style.display = 'none';
                            if (newPreview) newPreview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        if (img) img.src = '#';
                        if (noPreview) noPreview.style.display = 'block';
                        if (newPreview) newPreview.style.display = 'none';
                    }
                });
            }

            handleFileInput('#gpay_qr_file');
            handleFileInput('#phonepe_qr_file');

            // Clear upload button
            document.addEventListener('click', function(e){
                var clear = e.target.closest('.clear-upload-btn');
                if (!clear) return;
                var target = document.querySelector(clear.getAttribute('data-target'));
                if (target) {
                    target.value = '';
                    var cardBody = target.closest('.card-body') || target.closest('.form-group');
                    var container = cardBody && cardBody.querySelector('.qr-preview-container');
                    var noPreview = container && container.querySelector('.no-preview');
                    var newPreview = container && container.querySelector('.new-preview');
                    if (noPreview) noPreview.style.display = 'block';
                    if (newPreview) newPreview.style.display = 'none';
                }
            });
        })();
    </script>
@endpush

