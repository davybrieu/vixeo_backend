<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $profile = $this->route('profile');
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required, 'string', 'max:50',
                Rule::unique('profiles', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($profile?->id),
            ],
            /** Avatar identifier chosen in the app's built-in avatar set. */
            'avatar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_kid' => ['sometimes', 'boolean'],
            /** Maximum age rating allowed for this profile (e.g. 12, 16). Null = no limit. */
            'max_age_rating' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:21'],
            /** 4-digit PIN protecting the profile. Send null to remove it. */
            'pin' => ['sometimes', 'nullable', 'string', 'digits:4'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ];
    }
}
