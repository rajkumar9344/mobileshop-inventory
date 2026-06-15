<?php

namespace Modules\Setting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => ['required', 'string', 'max:15', 'regex:/^\+?[0-9]+$/'],
            'bank_name' => 'nullable|string|min:2|max:100',
            'bank_account' => ['nullable','string','min:6','max:50','regex:/^[A-Za-z0-9\-\/\s]+$/'],
            'bank_branch' => 'nullable|string|min:2|max:100',
            'bank_ifsc' => ['nullable','string','size:11','regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'company_gst' => 'nullable|string|max:20',
            'notification_email' => 'required|email|max:255',
            'company_address' => 'required|string|max:500',
            'default_currency_id' => 'required|numeric',
            'default_currency_position' => 'required|string|max:255',
            'footer_text' => 'nullable|string|max:255',
            'site_logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096|dimensions:min_width=300,min_height=80',
            'remove_site_logo' => 'nullable|boolean',
            'gpay_qr_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'phonepe_qr_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'remove_gpay_qr' => 'nullable|boolean',
            'remove_phonepe_qr' => 'nullable|boolean',
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'bank_name.min' => 'Bank name must be at least :min characters.',
            'bank_account.min' => 'Account number seems too short.',
            'bank_account.regex' => 'Account number contains invalid characters.',
            'bank_ifsc.size' => 'IFSC must be :size characters (e.g. ABCD0E12345).',
            'bank_ifsc.regex' => 'Please enter a valid IFSC code (e.g. ABCD0E12345).',
            'bank_branch.min' => 'Branch name must be at least :min characters.',
            'site_logo.dimensions' => 'Logo should be at least 300x80 pixels for better clarity.',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('access_settings');
    }
}
