<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSettingRequest extends StoreSettingRequest
{
    public function rules()
    {
        $rules = parent::rules();
        $rules['key'] = [
            'required',
            'string',
            'max:100',
            'regex:/^[a-z][a-z0-9_.]*$/',
            Rule::unique('settings', 'key')->ignore($this->route('setting')),
        ];

        return $rules;
    }
}
