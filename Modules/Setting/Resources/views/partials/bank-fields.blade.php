<div class="form-row">
    <div class="col-lg-3">
        <div class="form-group">
            <label for="bank_name">Bank Name</label>
            <input type="text" class="form-control" name="bank_name" id="bank_name" value="{{ old('bank_name', $settings->bank_name ?? '') }}" maxlength="100" oninput="validateBankName(this, 'bank-name-error')">
            <small id="bank-name-error" class="text-danger" style="display:{{ $errors->has('bank_name') ? 'block' : 'none' }};">{{ $errors->first('bank_name') }}</small>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="form-group">
            <label for="bank_account">Account No</label>
            <input type="text" class="form-control" name="bank_account" id="bank_account" value="{{ old('bank_account', $settings->bank_account ?? '') }}" maxlength="50" oninput="validateBankAccount(this, 'bank-account-error')">
            <small id="bank-account-error" class="text-danger" style="display:{{ $errors->has('bank_account') ? 'block' : 'none' }};">{{ $errors->first('bank_account') }}</small>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="form-group">
            <label for="bank_branch">Branch</label>
            <input type="text" class="form-control" name="bank_branch" id="bank_branch" value="{{ old('bank_branch', $settings->bank_branch ?? '') }}" maxlength="100" oninput="validateBankBranch(this, 'bank-branch-error')">
            <small id="bank-branch-error" class="text-danger" style="display:{{ $errors->has('bank_branch') ? 'block' : 'none' }};">{{ $errors->first('bank_branch') }}</small>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="form-group">
            <label for="bank_ifsc">IFSC Code</label>
            <input type="text" class="form-control" name="bank_ifsc" id="bank_ifsc" value="{{ old('bank_ifsc', $settings->bank_ifsc ?? '') }}" maxlength="50" oninput="validateIFSC(this, 'bank-ifsc-error')">
            <small id="bank-ifsc-error" class="text-danger" style="display:{{ $errors->has('bank_ifsc') ? 'block' : 'none' }};">{{ $errors->first('bank_ifsc') }}</small>
        </div>
    </div>
</div>

<!-- Payment QR Codes Section -->
<div class="row mt-4">
    <div class="col-12">
        <h6 class="text-muted mb-3"><i class="bi bi-qr-code"></i> Payment QR Codes</h6>
    </div>
</div>
<div class="row">
    <!-- GPay QR Card -->
    <div class="col-lg-6 col-md-6 mb-3">
        <div class="card shadow-sm h-100" style="border-radius: 10px; border: 1px solid #e0e0e0;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #4285f4, #34a853); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <span style="color: white; font-weight: bold; font-size: 14px;">G</span>
                    </div>
                    <div>
                        <h6 class="mb-0" style="font-weight: 600; color: #333;">GPay QR Code</h6>
                        <small class="text-muted">Upload your Google Pay QR</small>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <div class="flex-grow-1">
                        <div class="custom-file-upload mb-2">
                            <input type="file" name="gpay_qr_file" id="gpay_qr_file" accept="image/*" class="form-control" style="padding: 8px; border-radius: 6px;">
                        </div>
                        <small class="text-muted d-block">Supports: JPG, PNG, WebP</small>
                    </div>
                    <div class="qr-preview-container ml-3" style="min-width: 120px; text-align: center;">
                        @if(!empty($settings->gpay_qr))
                            <div class="existing-preview">
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 8px; border: 2px dashed #dee2e6;">
                                    <img src="{{ $settings->gpay_qr }}" alt="GPay QR" class="qr-preview-img" style="max-height: 100px; max-width: 100px; display: block; margin: 0 auto; border-radius: 4px;" onerror="this.style.display='none'">
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm mt-2 remove-qr-btn" data-which="gpay" style="border-radius: 20px; font-size: 11px; padding: 4px 12px;">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        @else
                            <div class="no-preview" style="background: #f8f9fa; border-radius: 8px; padding: 20px 15px; border: 2px dashed #dee2e6;">
                                <i class="bi bi-image" style="font-size: 24px; color: #adb5bd;"></i>
                                <div style="font-size: 11px; color: #adb5bd; margin-top: 4px;">No QR</div>
                            </div>
                            <div class="new-preview" style="display: none;">
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 8px; border: 2px dashed #28a745;">
                                    <img src="#" alt="GPay QR Preview" class="qr-preview-img" style="max-height: 100px; max-width: 100px; display: block; margin: 0 auto; border-radius: 4px;" />
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2 clear-upload-btn" data-target="#gpay_qr_file" style="border-radius: 20px; font-size: 11px; padding: 4px 12px;">
                                    <i class="bi bi-x"></i> Clear
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- PhonePe QR Card -->
    <div class="col-lg-6 col-md-6 mb-3">
        <div class="card shadow-sm h-100" style="border-radius: 10px; border: 1px solid #e0e0e0;">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-3">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #5f259f, #8c52ff); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                        <span style="color: white; font-weight: bold; font-size: 14px;">P</span>
                    </div>
                    <div>
                        <h6 class="mb-0" style="font-weight: 600; color: #333;">PhonePe QR Code</h6>
                        <small class="text-muted">Upload your PhonePe QR</small>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <div class="flex-grow-1">
                        <div class="custom-file-upload mb-2">
                            <input type="file" name="phonepe_qr_file" id="phonepe_qr_file" accept="image/*" class="form-control" style="padding: 8px; border-radius: 6px;">
                        </div>
                        <small class="text-muted d-block">Supports: JPG, PNG, WebP</small>
                    </div>
                    <div class="qr-preview-container ml-3" style="min-width: 120px; text-align: center;">
                        @if(!empty($settings->phonepe_qr))
                            <div class="existing-preview">
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 8px; border: 2px dashed #dee2e6;">
                                    <img src="{{ $settings->phonepe_qr }}" alt="PhonePe QR" class="qr-preview-img" style="max-height: 100px; max-width: 100px; display: block; margin: 0 auto; border-radius: 4px;" onerror="this.style.display='none'">
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm mt-2 remove-qr-btn" data-which="phonepe" style="border-radius: 20px; font-size: 11px; padding: 4px 12px;">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        @else
                            <div class="no-preview" style="background: #f8f9fa; border-radius: 8px; padding: 20px 15px; border: 2px dashed #dee2e6;">
                                <i class="bi bi-image" style="font-size: 24px; color: #adb5bd;"></i>
                                <div style="font-size: 11px; color: #adb5bd; margin-top: 4px;">No QR</div>
                            </div>
                            <div class="new-preview" style="display: none;">
                                <div style="background: #f8f9fa; border-radius: 8px; padding: 8px; border: 2px dashed #5f259f;">
                                    <img src="#" alt="PhonePe QR Preview" class="qr-preview-img" style="max-height: 100px; max-width: 100px; display: block; margin: 0 auto; border-radius: 4px;" />
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2 clear-upload-btn" data-target="#phonepe_qr_file" style="border-radius: 20px; font-size: 11px; padding: 4px 12px;">
                                    <i class="bi bi-x"></i> Clear
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
