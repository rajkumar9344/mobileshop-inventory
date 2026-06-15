<?php

namespace Modules\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreQuotationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_type' => 'required|in:existing,new',
            'customer_id' => 'required_if:customer_type,existing|nullable|numeric',
            'customer_name' => 'required_if:customer_type,new|string|max:255',
            'contact_phone' => ['nullable','string','max:15','regex:/^\+?[0-9]+$/'],
            'contact_email' => 'nullable|email:rfc,strict|max:255',
            'contact_address' => 'nullable|string|max:1000',
            'reference' => 'required|string|max:255',
            'tax_percentage' => 'required|integer|min:0|max:100',
            'discount_percentage' => 'required|integer|min:0|max:100',
            'shipping_amount' => 'required|numeric',
            'total_amount' => 'required|numeric',
            'status' => 'nullable|string|max:255',
            'reduce_stock' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Normalize incoming values before validation and sanitize numeric fields.
     */

    /**
     * Strip formatting from numeric inputs so validation accepts formatted currency.
     */
    protected function sanitizeNumericFields()
    {
        $numeric = [
            'overall_gross_amount', 'overall_taxable_amount', 'overall_amount', 'overall_net_rate',
            'overall_other', 'overall_adj', 'total_amount', 'shipping_amount'
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

    protected function prepareForValidation()
    {
        // existing normalizations
        if (is_array($this->customer_name)) {
            $this->merge(['customer_name' => reset($this->customer_name)]);
        }

        if (is_array($this->customer_id)) {
            $this->merge(['customer_id' => reset($this->customer_id)]);
        }
        
        if ($this->has('customer_type') && $this->customer_type === 'existing') {
            $this->request->remove('customer_name');
        }

        if ($this->has('customer_type') && $this->customer_type === 'new') {
            $this->request->remove('customer_id');
        }

        // sanitize numeric fields
        $this->sanitizeNumericFields();
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create_quotations');
    }

    /**
     * Called when validation fails — log request for debugging and rethrow.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     */
    protected function failedValidation(Validator $validator)
    {
        parent::failedValidation($validator);
    }
}

