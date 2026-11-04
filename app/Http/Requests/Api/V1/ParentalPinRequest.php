<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ParentalControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Set or change (PUT /pin) or remove (POST /disable) the parental PIN. Once a PIN exists, the current
 * PIN or the account password is needed (password = "code oublié").
 */
class ParentalPinRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            /** Current parental PIN. Required when one is set, unless `password` is given. */
            'current_pin' => ['sometimes', 'nullable', 'string', 'digits:4'],
            /** Account password, if the parental PIN was forgotten. */
            'password' => ['sometimes', 'nullable', 'string', 'current_password:sanctum'],
        ];

        if ($this->isMethod('put')) {
            /** New 4-digit parental PIN. */
            $rules['pin'] = ['required', 'string', 'digits:4'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->user();

            if ($validator->errors()->isNotEmpty() || ! $user->hasParentalControl() || $this->filled('password')) {
                return;
            }

            try {
                app(ParentalControl::class)->authorize($user, $this->input('current_pin'), 'current_pin');
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $validator->errors()->add($field, $messages[0]);
                }
            }
        }];
    }
}
