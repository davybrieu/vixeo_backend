<?php

namespace App\Http\Requests\Api\V1;

class CreditsRequest extends CatalogPageRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            /**
             * `cast` (default): the directors or creators on the first page, then the cast. `crew`:
             * « Équipe technique », everyone but the actors, with their jobs in French.
             */
            'group' => ['sometimes', 'string', 'in:cast,crew'],
        ];
    }

    public function crew(): bool
    {
        return $this->validated('group', 'cast') === 'crew';
    }
}
