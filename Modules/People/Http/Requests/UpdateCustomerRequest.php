<?php

namespace Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function prepareForValidation()
    {
        foreach (['opening_balance', 'excess_amount', 'credit_limit'] as $f) {
            if ($this->has($f)) {
                $clean = preg_replace('/[^0-9.\\-]/', '', (string) $this->input($f));
                $this->merge([$f => $clean === '' ? null : $clean]);
            }
        }
    }

    public function rules()
    {
        $customerId = $this->route('customer') ? $this->route('customer')->id : null;
        return [
            'customer_name'   => 'required|string|max:80',
            'customer_code'   => 'required|alpha_num|max:10|unique:customers,customer_code,'.$customerId,
            'customer_phone'  => ['required','string','max:10','regex:/^[0-9]+$/','unique:customers,customer_phone,'.$customerId],
            'customer_email'  => ['nullable','email:rfc,strict','max:50','unique:customers,customer_email,'.$customerId],
            'city'            => 'nullable|string|max:30',
            'state'           => 'required|string|max:30',
            'address'         => 'nullable|string|max:200',
            'area'            => 'required|string|max:30',
            'pincode'         => 'nullable|digits_between:1,10',
            'vat_id'          => 'nullable|alpha_num|max:20',
            'opening_balance' => ['required','regex:/^-?\\d+(?:\\.\\d{1,2})?$/','max:15'],
            'excess_amount'   => ['nullable','regex:/^-?\\d+(?:\\.\\d{1,2})?$/','max:15'],
            'credit_limit'    => ['required','regex:/^\\d+(?:\\.\\d{1,2})?$/','max:15'],
            'lock'            => 'required|in:Yes,No',
            'outstanding'     => 'required|in:Yes,No',
            'is_active'       => 'required|boolean',
            'account_id'      => 'nullable|alpha_num|max:10',
            'remarks'         => 'nullable|string|max:200',
        ];
    }
}
