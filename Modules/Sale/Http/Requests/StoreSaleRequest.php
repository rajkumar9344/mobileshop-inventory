<?php

namespace Modules\Sale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSaleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $isDraft = $this->input('is_draft', 0) == 1;

        $rules = [
            // new client-requested fields
            'area' => 'nullable|string|max:30',
            'vehicle_name' => 'nullable|string|max:60',
            'vehicle_no' => 'nullable|string|max:30',
            // allow negative balances (leading minus) as customers can have credits
            'opening_balance' => $isDraft ? 'nullable|regex:/^-?[0-9.,]+$/' : 'required|regex:/^-?[0-9.,]+$/',
            'phone' => ['nullable','string','max:10','regex:/^[0-9]+$/'],
            'discount_type' => 'nullable|alpha|size:1',
            'discount_amount' => 'nullable|numeric|lte:overall_amount',
            // 'reference' => 'required|string|max:255', // Auto-generated in model
            'tax_percentage' => $isDraft ? 'nullable|integer|min:0|max:100' : 'required|integer|min:0|max:100',
            'discount_percentage' => $isDraft ? 'nullable|integer|min:0|max:100' : 'required|integer|min:0|max:100',
            'shipping_amount' => $isDraft ? 'nullable|numeric' : 'required|numeric',
            'total_amount' => $isDraft ? 'nullable|numeric' : 'required|numeric',
            'status' => 'required|string|max:255',
            'payment_method' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000'
        ];

        // Conditional rules for non-drafts
        if (!$isDraft) {
            $rules['customer_id'] = 'required|numeric';
            $rules['bill_type'] = 'required|string|in:Cash,Credit';
            // Ensure paid amount is numeric and not greater than the computed net rate
            $rules['paid_amount'] = 'required_if:bill_type,Cash|nullable|numeric|lte:overall_amount';
        } else {
            $rules['customer_id'] = 'nullable|numeric';
            $rules['bill_type'] = 'nullable|string|in:Cash,Credit';
            $rules['paid_amount'] = 'nullable|numeric';
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    protected function prepareForValidation()
    {
        $this->merge($this->normalizeNumericFields([
            'overall_amount',
            'paid_amount',
            'discount_amount',
            'opening_balance',
            'shipping_amount',
            'total_amount',
        ]));
    }

    /**
     * Normalize numeric fields by stripping formatting (commas, spaces, currency symbols).
     */
    private function normalizeNumericFields(array $fields)
    {
        $normalized = [];
        foreach ($fields as $field) {
            if ($this->has($field)) {
                $value = (string) $this->input($field);
                $value = str_replace([',', ' ', '₹', '$', '€'], '', $value);
                $normalized[$field] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create_sales');
    }
}
