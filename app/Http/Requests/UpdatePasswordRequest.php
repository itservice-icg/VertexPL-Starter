<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'current_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (! Hash::check($value, $this->user()->password)) {
                        $fail('รหัสผ่านปัจจุบันไม่ถูกต้อง');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'different:current_password',
                'confirmed',
                Password::min(14)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function messages()
    {
        return [
            'password.different' => 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านปัจจุบัน',
        ];
    }
}
