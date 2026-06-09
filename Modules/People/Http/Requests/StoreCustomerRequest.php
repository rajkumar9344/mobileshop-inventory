<?php

namespace Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function prepareForValidation()
    {
        // sanitize numeric/currency-like inputs
        $fields = ['opening_balance', 'excess_amount', 'credit_limit', 'cash_discount', 'additional_discount', 'discount_percent'];
        foreach ($fields as $f) {
            if ($this->has($f)) {
                $clean = preg_replace('/[^0-9.\\-]/', '', (string) $this->input($f));
                $this->merge([$f => $clean === '' ? null : $clean]);
            }
        }
    }

    public function rules()
    {
        return [
            'customer_name'  => 'required|string|max:80',
            'customer_code'  => 'required|alpha_num|max:10|unique:customers,customer_code',
            'customer_phone' => ['required','string','max:10','regex:/^[0-9]+$/','unique:customers,customer_phone'],
            'customer_email' => ['nullable','email','max:50','unique:customers,customer_email'],
            'city'           => 'nullable|string|max:30',
            'state'          => 'required|string|max:30',
            'country'        => 'nullable|string|max:30',
            'address'        => 'nullable|string|max:200',
            'area'           => 'required|string|max:30',
            'pincode'        => 'nullable|digits_between:1,10',
            'opening_balance'=> ['required','regex:/^-?\\d+(?:\\.\\d{1,2})?$/','max:15'],
            'excess_amount'  => ['nullable','regex:/^-?\\d+(?:\\.\\d{1,2})?$/','max:15'],
            'credit_limit'   => ['required','regex:/^\\d+(?:\\.\\d{1,2})?$/','max:15'],
            'cash_discount'  => 'nullable|numeric|min:0|max:100',
            'additional_discount'  => 'nullable|numeric|min:0|max:100',
            'discount_percent'=> 'required|numeric|min:0|max:100',
            'terms_days'     => 'required|integer|min:0|max:999',
            'lock'           => 'required|in:Yes,No',
            'outstanding'    => 'required|in:Yes,No',
            'is_active'      => 'required|boolean',
            'salesman'       => 'required|string|max:20',
            'account_id'     => 'nullable|alpha_num|max:10',
            'lr_through'     => 'nullable|string|max:200',
            'remarks'        => 'nullable|string|max:200',
            'gst_no'         => 'nullable|alpha_num|max:15|unique:customers,gst_no',
            'pan_no'         => 'nullable|alpha_num|size:10|unique:customers,pan_no',
            'aadhar_no'      => 'nullable|digits:12|unique:customers,aadhar_no',
        ];
    }
}
