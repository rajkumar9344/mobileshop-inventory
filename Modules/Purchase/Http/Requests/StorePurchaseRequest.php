<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'supplier_id' => 'required|numeric',
            'reference' => 'required|string|max:255',
            'ref_date' => 'required|date',
            'area' => 'nullable|string|max:30',
            'balance' => 'nullable|numeric',
            'invoice_no' => 'required|string|max:20',
            'invoice_date' => 'required|date',
            'days' => 'required|integer|min:0|max:999',
            'due_date' => 'nullable|date',
            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'paid_amount' => [
                'nullable',
                'numeric',
                'min:0',
                function($attribute, $value, $fail) {
                    // Only validate relation to total_amount when a paid value is provided
                    if ($value === null) return;
                    $discount_amount = $this->discount_amount ?? 0;
                    if (($value + $discount_amount) > $this->total_amount) {
                        $fail('The sum of paid amount and discount amount must not be greater than the total amount.');
                    }
                }
            ],
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'required|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create_purchases');
    }
}