<?php

namespace App\Http\Requests\Api\V1\Social;

use App\Models\ProfileList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveListRequest extends FormRequest
{
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'title' => ['required', 'string', 'max:'.ProfileList::MAX_TITLE],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.ProfileList::MAX_DESCRIPTION],
            /** `top`: numbered, 10 titles at most (« Mon top 10 »); `list`: in the chosen order, 100 at most. */
            'kind' => [$creating ? 'required' : 'sometimes', 'string', Rule::in([ProfileList::TOP, ProfileList::LIST])],
            /**
             * The titles, in order (`movie:550`, `tv:1399`). Left out when changing a list: kept.
             *
             * @var list<string>
             */
            'items' => ['sometimes', 'array', 'max:'.ProfileList::MAX_LIST],
            'items.*' => ['string', 'regex:/^(movie|tv):\d{1,9}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' && $field === 'description' ? null : $value]);
            }
        }
    }

    /** @return list<string>|null */
    public function items(): ?array
    {
        $items = $this->validated('items');

        return is_array($items) ? array_values(array_map('strval', $items)) : null;
    }
}
