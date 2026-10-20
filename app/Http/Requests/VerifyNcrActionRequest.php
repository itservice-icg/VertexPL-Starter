<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyNcrActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version_no' => ['required', 'integer', 'min:1'],
            'result' => ['required', 'in:PASSED,FAILED'],
            'corrective_action_id' => ['nullable', 'integer', 'exists:ncr_corrective_actions,id'],
            'verification_detail' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'result.required' => 'กรุณาเลือกผลการตรวจยืนยัน',
            'result.in' => 'ผลการตรวจยืนยันไม่ถูกต้อง',
            'corrective_action_id.exists' => 'ไม่พบ Corrective Action ที่เลือก',
            'verification_detail.required' => 'กรุณาระบุรายละเอียดการตรวจยืนยัน',
        ];
    }
}
