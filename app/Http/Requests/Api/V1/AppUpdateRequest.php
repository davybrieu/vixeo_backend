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
            /** Platform of the installed app (`android` also covers Android TV). */
            'platform' => ['required', Rule::enum(AppPlatform::class)],
            /** Build number of the installed app (`CFBundleVersion` on iOS, `versionCode` on Android). */
            'build' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
