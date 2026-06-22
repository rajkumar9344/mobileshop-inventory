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
            'product_name' => ['required', 'string', 'max:50'],
            'alternative_number' => ['nullable', 'string', 'max:50', 'unique:products,alternative_number,' . $this->product->id],
            'product_unit' => ['required', 'string', 'max:20'],
            'product_quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'product_stock_alert' => ['required', 'integer', 'min:0', 'max:9999'],
            'product_order_tax' => ['nullable', 'integer', 'min:0', 'max:99'],
            'product_tax_type' => ['nullable', 'in:1,2'],
            'product_note' => ['nullable', 'string', 'max:300'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
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
        return Gate::allows('edit_products');
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
