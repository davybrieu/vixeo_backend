<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Off: the plug-in stays installed, but its rows and streams are not shown. */
            'enabled' => ['required', 'boolean'],
        ];
    }
}
