<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeviceStartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Shown on the phone before approving, and used as the token name ("Vixeo TV"). */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
