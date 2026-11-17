<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InstallAddonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /**
             * Address of the plug-in's manifest (`https://…/manifest.json`, or `stremio://…`). A
             * configured plug-in's address may hold a personal key: it is stored encrypted and never
             * sent back.
             */
            'url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function addonUrl(): string
    {
        return trim((string) $this->validated('url'));
    }
}
