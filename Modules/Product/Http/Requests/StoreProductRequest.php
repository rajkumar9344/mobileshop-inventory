<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'product_name' => ['required', 'string', 'max:50'],
            'product_code' => ['required', 'string', 'max:50', 'unique:products,product_code'],
            'alternative_number' => ['nullable', 'string', 'max:50', 'unique:products,alternative_number'],
            'product_unit' => ['required', 'string', 'max:20'],
            'product_quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'product_cost' => ['required', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'product_price' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'buy_price' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'list_price' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'product_stock_alert' => ['required', 'integer', 'min:0', 'max:9999'],
            'product_order_tax' => ['nullable', 'integer', 'min:0', 'max:99'],
            'product_tax_type' => ['nullable', 'in:1,2'],
            'product_note' => ['nullable', 'string', 'max:300'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'open_quantity' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive,active,inactive'],
        ];

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('create_products');
    }

    /**
     * Prepare the data for validation.
     * Normalize price inputs by removing currency symbols, commas and extra dots.
     */
    protected function prepareForValidation()
    {
        $priceFields = ['buy_price', 'product_cost', 'product_price', 'list_price'];

        $cleaned = [];
        foreach ($priceFields as $field) {
            $val = $this->input($field);
            if ($val !== null && $val !== '') {
                // Remove any characters except digits and dot
                $v = preg_replace('/[^0-9.]/', '', (string)$val);

                // If multiple dots, keep only the first and remove the rest
                if (substr_count($v, '.') > 1) {
                    $parts = explode('.', $v);
                    $int = array_shift($parts);
                    $dec = implode('', $parts); // join remaining parts
                    $v = $int . '.' . $dec;
                }

                // Limit decimals to 2 places
                if (strpos($v, '.') !== false) {
                    list($intPart, $decPart) = explode('.', $v, 2);
                    $decPart = substr($decPart, 0, 2);
                    $v = $intPart . ($decPart !== '' ? '.' . $decPart : '');
                }

                // Trim total length to 10 chars (safety)
                $v = substr($v, 0, 10);

                // Ensure we don't return empty string
                if ($v === '' || $v === '.') {
                    $v = null;
                }

                $cleaned[$field] = $v;
            }
        }

        if (!empty($cleaned)) {
            $this->merge($cleaned);
        }
    }

    /**
     * Custom attribute names for validation messages.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'alternative_number' => "Equivalent Product's Code",
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Flash the first validation message as a SweetAlert toast and redirect back with errors.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $message = $validator->errors()->first();

        // flash a toast warning (middleware looks for toast_warning session key)
        session()->flash('toast_warning', $message);

        $response = redirect()->back()
            ->withErrors($validator)
            ->withInput();

        throw new HttpResponseException($response);
    }
}