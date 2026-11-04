<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ParentalControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * The parental PIN comes in the `X-Parental-Pin` header: a DELETE has no body, and a PIN
 * must never be put in the URL (it would end up in server logs).
 */
class DeleteProfileRequest extends FormRequest
{
    public const PIN_HEADER = 'X-Parental-Pin';

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            try {
                app(ParentalControl::class)->authorize($this->user(), $this->header(self::PIN_HEADER));
            } catch (ValidationException $e) {
                $validator->errors()->add('parental_pin', $e->errors()['parental_pin'][0]);
            }
        }];
    }
}
