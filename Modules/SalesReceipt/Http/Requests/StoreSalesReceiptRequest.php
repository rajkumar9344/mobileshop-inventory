<?php

namespace Modules\SalesReceipt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReceiptRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        $rules = [
            'date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'particular' => 'nullable|string|max:100',
            // UI requires a payment mode (Cash, Cheque, Bank Transfer, UPI Payment, Others)
            'payment_mode' => 'required|string|max:50',
            // top-level amount field added in the UI — validate as decimal (up to 2 d.p.) and max length
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            // ensure submitted opening balance (for display) is numeric if present
            'opening_balance' => ['nullable', 'numeric'],
            'apply_to_opening' => ['nullable', 'boolean'],
        ];

        // If apply_to_opening is not checked, lines must be present. When checked, lines may be empty.
        if (! $this->boolean('apply_to_opening')) {
            $rules['lines'] = 'required|array|min:1';
            $rules['lines.*.sale_id'] = 'required|exists:sales,id';
            $rules['lines.*.payment_amount'] = ['required', 'numeric', 'min:0', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'];
            $rules['lines.*.discount_amount'] = ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'];
            $rules['lines.*.is_settled'] = ['nullable', 'boolean'];
        } else {
            $rules['lines'] = 'nullable|array';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'lines.required' => 'At least one sales bill must be selected.',
            'lines.*.payment_amount.required' => 'Payment amount is required for each selected bill.',
            'lines.*.payment_amount.regex' => 'Payment amount must be a decimal with up to 2 decimal places and max 13 digits before decimal.',
            'lines.*.discount_amount.regex' => 'Discount amount must be a decimal with up to 2 decimal places and max 13 digits before decimal.',
        ];
    }
}
