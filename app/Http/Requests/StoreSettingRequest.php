<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettingRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('system.settings');
    }

    public function rules()
    {
        $valueRules = ['nullable', 'string', 'max:10000'];

        if ($this->input('type') === 'integer') {
            $valueRules[] = 'integer';
        } elseif ($this->input('type') === 'boolean') {
            $valueRules[] = Rule::in(['0', '1']);
        } elseif ($this->input('type') === 'json') {
            $valueRules[] = 'json';
        }

        return [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]*$/', 'unique:settings,key'],
            'label' => ['required', 'string', 'max:150'],
            'group' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_-]*$/'],
            'type' => ['required', Rule::in(['string', 'integer', 'boolean', 'json'])],
            'value' => $valueRules,
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
