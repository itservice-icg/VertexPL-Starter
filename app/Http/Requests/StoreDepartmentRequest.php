<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('departments.create');
    }

    public function rules()
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:departments,code'],
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
