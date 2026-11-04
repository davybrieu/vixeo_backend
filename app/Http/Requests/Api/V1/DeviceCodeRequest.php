<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeviceCodeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Code shown on the TV ("K7F-29Q"; dash and case don't matter). */
            'user_code' => ['required', 'string', 'max:20'],
        ];
    }
}
