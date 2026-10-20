<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCorrectiveActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'version_no' => ['required', 'integer', 'min:1'],
            'action_type' => ['required', 'in:CORRECTION,CORRECTIVE,PREVENTIVE'],
            'root_cause_type_id' => ['nullable', Rule::exists('root_cause_types', 'id')->where('is_active', 1)],
            'root_cause_detail' => ['nullable', 'string', 'max:10000'],
            'immediate_correction' => ['nullable', 'string', 'max:10000'],
            'corrective_action_detail' => ['required', 'string', 'max:10000'],
            'preventive_action_detail' => ['nullable', 'string', 'max:10000'],
            'responsible_department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', 1)],
            'owner_user_id' => [Rule::requiredIf(fn () => $this->route('ncr')?->current_status !== 'ACTION_IN_PROGRESS'), 'nullable', Rule::exists('users', 'id')->where('is_active', 1)->where('department_id', $this->input('responsible_department_id'))],
            'responsible_acknowledged_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'completed_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'action_status' => ['required', 'in:OPEN,IN_PROGRESS,COMPLETED'],
            'completion_note' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,docx'],
        ];
    }

}
