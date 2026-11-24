<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ProfileCovers;
use Illuminate\Foundation\Http\FormRequest;

/** A profile cover, as a multipart field `cover`. */
class ProfileCoverRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** JPEG, PNG or WebP, 600 × 200 to 4,096 pixels a side, 8 MB at most. Its central 3:1 part is kept. */
            'cover' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.ProfileCovers::MAX_KILOBYTES,
                sprintf(
                    'dimensions:min_width=%d,min_height=%d,max_width=%d,max_height=%d',
                    ProfileCovers::MIN_WIDTH, ProfileCovers::MIN_HEIGHT, ProfileCovers::MAX_SIDE, ProfileCovers::MAX_SIDE,
                ),
            ],
        ];
    }
}
