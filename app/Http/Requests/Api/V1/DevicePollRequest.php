<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DevicePollRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** `device_code` returned by POST /auth/device. Secret: keep it on the TV. */
            'device_code' => ['required', 'string', 'max:100'],
        ];
    }
}
