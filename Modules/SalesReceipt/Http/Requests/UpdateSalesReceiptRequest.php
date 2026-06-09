<?php

namespace Modules\SalesReceipt\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesReceiptRequest extends FormRequest
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
            'payment_mode' => 'nullable|string|max:50',
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\\d{1,13}(?:\\.\\d{1,2})?$/'],
            'opening_balance' => ['nullable', 'numeric'],
            'apply_to_opening' => ['nullable', 'boolean'],
        ];

        if (! $this->boolean('apply_to_opening')) {
            $rules['lines'] = 'required|array|min:1';
            $rules['lines.*.sale_id'] = 'required|exists:sales,id';
            $rules['lines.*.payment_amount'] = ['required', 'numeric', 'min:0', 'regex:/^\\d{1,13}(?:\\.\\d{1,2})?$/'];
            $rules['lines.*.discount_amount'] = ['nullable', 'numeric', 'min:0', 'regex:/^\\d{1,13}(?:\\.\\d{1,2})?$/'];
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
        ];
    }
}
