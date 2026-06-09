<?php

namespace Modules\PurchasesReceipt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchasesReceiptRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        $rules = [
            'date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'particular' => 'nullable|string|max:100',
            // UI requires a payment mode (Cash, Cheque, Bank Transfer, UPI Payment, Others)
            'payment_mode' => 'required|string|max:50',
            // top-level amount field added in the UI — validate as decimal (up to 2 d.p.) and max length
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/'],
            // opening balance may be sent when editing existing receipts
            'opening_balance' => ['nullable', 'numeric'],
            'apply_to_opening' => ['nullable', 'boolean'],
        ];

        // If apply_to_opening is not checked, lines must be present. When checked, lines may be empty.
        if (! $this->boolean('apply_to_opening')) {
            $rules['lines'] = 'required|array|min:1';
            $rules['lines.*.purchase_id'] = 'required|exists:purchases,id';
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
            'lines.required' => 'At least one purchases bill must be selected.',
            'lines.*.payment_amount.required' => 'Payment amount is required for each selected bill.',
            'lines.*.payment_amount.regex' => 'Payment amount must be a decimal with up to 2 decimal places and max 13 digits before decimal.',
            'lines.*.discount_amount.regex' => 'Discount amount must be a decimal with up to 2 decimal places and max 13 digits before decimal.',
        ];
    }
}