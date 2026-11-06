<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AppPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(AppPlatform::class)],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/', 'max:32'],
            'build' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
