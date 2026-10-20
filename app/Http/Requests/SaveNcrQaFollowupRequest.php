<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNcrQaFollowupRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'version_no' => ['required', 'integer', 'min:1'],
            'followup_date' => ['required', 'date'],
            'effectiveness_result' => ['required', 'in:EFFECTIVE,NOT_EFFECTIVE,NEED_MORE_TIME'],
            'followup_detail' => ['nullable', 'string', 'max:10000'],
            'return_reason' => ['nullable', 'required_if:effectiveness_result,NOT_EFFECTIVE,NEED_MORE_TIME', 'string', 'max:10000'],
            'next_followup_date' => ['nullable', 'date', 'after_or_equal:followup_date'],
            'checklist_confirmed' => ['exclude_unless:effectiveness_result,EFFECTIVE', 'required', 'accepted'],
        ];
    }
    public function messages(): array
    {
        return [
            'followup_date.required' => 'กรุณาระบุวันที่ติดตาม',
            'effectiveness_result.required' => 'กรุณาเลือกผลการติดตาม / การปิด NCR',
            'return_reason.required_if' => 'กรุณาระบุเหตุผลเมื่อยังไม่สามารถปิด NCR ได้',
            'next_followup_date.after_or_equal' => 'วันที่ติดตามครั้งถัดไปต้องไม่ก่อนวันที่ติดตาม',
            'checklist_confirmed.required' => 'กรุณายืนยัน checklist ก่อนปิด NCR',
            'checklist_confirmed.accepted' => 'กรุณายืนยัน checklist ก่อนปิด NCR',
        ];
    }
}
