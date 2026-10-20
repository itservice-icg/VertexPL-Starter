<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('roles.update')
            && $this->user()->can('permissions.manage');
    }

    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($this->route('role')),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where(function ($query) {
                    $query->where('name', '!=', 'exports.create');
                }),
            ],
        ];
    }
}
