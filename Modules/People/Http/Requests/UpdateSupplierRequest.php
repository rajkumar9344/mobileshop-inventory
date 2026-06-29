<?php

namespace Modules\People\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function prepareForValidation()
    {
        $fields = ['open_balance', 'excess_amount', 'credit_limit'];
        foreach ($fields as $f) {
            if ($this->has($f)) {
                $clean = preg_replace('/[^0-9.\\-]/', '', (string) $this->input($f));
                $this->merge([$f => $clean === '' ? null : $clean]);
            }
        }
        if ($this->has('due_days')) {
            $days = preg_replace('/[^0-9]/', '', (string) $this->input('due_days'));
            $this->merge(['due_days' => $days === '' ? 0 : (int) $days]);
        }
    }

    public function rules()
    {
        $supplierId = $this->route('supplier') ? $this->route('supplier')->id : null;
        return [
            'supplier_name'  => 'required|string|max:80',
            'supplier_code'  => ['required', 'alpha_num', 'max:10', Rule::unique('suppliers', 'supplier_code')->ignore($supplierId)],
            'supplier_phone' => ['required', 'string', 'max:15', 'regex:/^\+?[0-9]+$/', Rule::unique('suppliers', 'supplier_phone')->ignore($supplierId)],
            'supplier_email' => ['nullable', 'email:rfc,strict', 'max:50', 'lowercase', Rule::unique('suppliers', 'supplier_email')->ignore($supplierId)],
            'trn'            => 'nullable|string|max:30',
            'area'           => 'required|string|max:30',
            'state'          => 'nullable|string|max:30',
            'city'           => 'nullable|string|max:30',
            'address'        => 'nullable|string|max:200',
            'open_balance'   => 'nullable|numeric',
            'credit_limit'   => 'nullable|numeric',
            'excess_amount'  => 'nullable|numeric',
            'tax_percent'    => 'nullable|numeric|min:0|max:100',
            'due_days'       => 'nullable|integer|min:0|max:999',
            'status'         => 'required|in:active,inactive',
            'remarks'        => 'nullable|string|max:200',
        ];
    }
}
