<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UpdateUserRequest extends FormRequest
{
    public function authorize()
    {
        if (! $this->user()->can('users.update')) {
            return false;
        }

        return ! $this->assignsSuperAdminRole() || $this->user()->can('permissions.manage');
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at')->where(function ($query) {
                        $query->where('is_active', true)
                            ->orWhere('id', optional($this->route('user'))->department_id);
                    });
                }),
            ],
            'department_role' => ['required', Rule::exists('department_roles', 'code')],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(14)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('department_role') === 'DEPARTMENT_HEAD' && ! $this->filled('department_id')) {
                $validator->errors()->add('department_id', 'บทบาทนี้ต้องผูกกับหน่วยงานก่อน');
            }
        });
    }

    private function assignsSuperAdminRole(): bool
    {
        return Role::query()->whereKey($this->input('role_id'))->where('name', 'Super Admin')->exists();
    }
}
