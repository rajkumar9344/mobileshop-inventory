<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            // Product names are no longer required to be unique across products
            'product_name' => ['required', 'string', 'max:50'],
            // default product_code rule (may be replaced below for numeric symbologies)
            'product_code' => ['required', 'string', 'max:50', 'unique:products,product_code,' . $this->product->id],
            // Equivalent product code must be unique across products when provided (exclude current)
            'alternative_number' => ['nullable', 'string', 'max:50', 'unique:products,alternative_number,' . $this->product->id],
            'product_barcode_symbology' => ['required', 'string', 'max:50'],
            'product_unit' => ['required', 'string', 'max:20'],
            'product_quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            // Limit total input length to 10 chars: up to 7 integer digits + optional "." + up to 2 decimals
            'product_cost' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'product_price' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'buy_price' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'list_price' => ['nullable', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'product_stock_alert' => ['required', 'integer', 'min:0', 'max:9999'],
            'product_order_tax' => ['nullable', 'integer', 'min:0', 'max:99'],
            'product_tax_type' => ['nullable', 'in:1,2'],
            'product_note' => ['nullable', 'string', 'max:300'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['required', 'integer', 'exists:subcategories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'rack_no' => ['required', 'string', 'max:255'],
            'bin_no' => ['required', 'string', 'max:255'],
            // HSN is mandatory as per BRD unless user explicitly marks it unknown
            'hsn' => ['required_unless:hsn_unknown,1', 'regex:/^[0-9]{1,15}$/'],
            'mrp' => ['required', 'numeric', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'open_quantity' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:Active,Inactive,active,inactive']
        ];

        // If user selected a numeric-only symbology (EAN/UPC), enforce numeric + length rules
        $sym = strtoupper($this->input('product_barcode_symbology', ''));
        if ($sym) {
            switch ($sym) {
                case 'EAN13':
                    $rules['product_code'] = ['required', 'digits:13', 'unique:products,product_code,' . $this->product->id];
                    break;
                case 'EAN8':
                    $rules['product_code'] = ['required', 'digits:8', 'unique:products,product_code,' . $this->product->id];
                    break;
                case 'UPCA':
                    $rules['product_code'] = ['required', 'digits:12', 'unique:products,product_code,' . $this->product->id];
                    break;
                case 'UPCE':
                    $rules['product_code'] = ['required', 'regex:/^\d{6}(?:\d{2})?$/', 'unique:products,product_code,' . $this->product->id];
                    break;
                default:
                    // keep default (alphanumeric allowed)
                    break;
            }
        }

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('edit_products');
    }

    /**
     * Prepare the data for validation.
     * Normalize price inputs by removing currency symbols, commas and extra dots.
     */
    protected function prepareForValidation()
    {
        $priceFields = ['buy_price', 'product_cost', 'product_price', 'list_price', 'mrp'];

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
            'hsn' => 'HSN',
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
