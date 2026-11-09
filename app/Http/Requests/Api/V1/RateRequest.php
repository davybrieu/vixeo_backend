<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Rating from 0.5 to 10, by half points (TMDb scale). */
            'value' => ['required', 'numeric', 'min:0.5', 'max:10', 'multiple_of:0.5'],
        ];
    }

    public function value(): float
    {
        return (float) $this->validated('value');
    }
}
