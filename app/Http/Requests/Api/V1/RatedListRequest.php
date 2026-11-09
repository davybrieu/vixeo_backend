<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RatedType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RatedListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Movies, TV shows or episodes; every type when left out. */
            'type' => ['sometimes', Rule::enum(RatedType::class)],
            /** Page number. */
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            /** Ratings per page (1 to 500, 20 by default). */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function type(): ?RatedType
    {
        $type = $this->validated('type');

        return is_string($type) ? RatedType::from($type) : null;
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 20);
    }
}
