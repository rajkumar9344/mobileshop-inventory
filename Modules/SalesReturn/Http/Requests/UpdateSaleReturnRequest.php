<?php

namespace Modules\SalesReturn\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSaleReturnRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_id' => 'required|numeric',
            'area' => 'nullable|string|max:30',
            'opening_balance' => 'nullable|numeric',
            'phone' => ['nullable','string','max:15','regex:/^\+?[0-9]+$/'],
            'excess_amount' => 'nullable|numeric',
            'reference' => 'required|string|max:255',
            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            // Overall calculation fields (optional, provided by product-cart)
            'overall_nos' => 'nullable|integer',
            'overall_quantity' => 'nullable|numeric',
            'overall_gross_amount' => 'nullable|numeric',
            'overall_taxable_amount' => 'nullable|numeric',
            'overall_cgst' => 'nullable|numeric',
            'overall_sgst' => 'nullable|numeric',
            'overall_igst' => 'nullable|numeric',
            'overall_tax_amount' => 'nullable|numeric',
            'overall_tcs_percent' => 'nullable|integer',
            'overall_amount' => 'nullable|numeric',
            'overall_other' => 'nullable|numeric',
            'overall_adj' => 'nullable|numeric',
            'overall_net_rate' => 'nullable|numeric',
            'create_receipt' => 'nullable|boolean',
            'total_amount' => 'required|numeric',
            'paid_amount' => 'nullable|numeric|max:' . $this->sale_return->total_amount,
            'status' => 'nullable|string|max:255',
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
        return Gate::allows('edit_sale_returns');
    }

    /**
     * Prepare the data for validation by stripping formatting from numeric fields.
     */
    protected function prepareForValidation()
    {
        $numeric = [
            'overall_gross_amount', 'overall_taxable_amount', 'overall_amount', 'overall_net_rate',
            'overall_other', 'overall_adj', 'total_amount', 'paid_amount', 'shipping_amount',
            'opening_balance', 'excess_amount', 'overall_cgst', 'overall_sgst', 'overall_igst', 'overall_tax_amount'
        ];

        $data = $this->all();

        foreach ($numeric as $field) {
            if ($this->has($field) && $this->get($field) !== null && $this->get($field) !== '') {
                $clean = preg_replace('/[^0-9.\-]/', '', (string) $this->get($field));
                $data[$field] = $clean === '' ? null : floatval($clean);
            }
        }

        $this->merge($data);
    }
}
