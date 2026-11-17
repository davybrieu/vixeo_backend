<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OrderAddonsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /**
             * The account's own plug-ins, first first (ids of `GET /addons`). The Vixeo catalog and
             * the plug-ins of every account keep their place.
             *
             * @var list<string>
             */
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['string', 'max:20'],
        ];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_values(array_map(strval(...), (array) $this->validated('ids')));
    }
}
