<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ProfilePhotos;
use Illuminate\Foundation\Http\FormRequest;

/** A profile photo, as a multipart field `photo`. */
class ProfilePhotoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** JPEG, PNG or WebP, 128 to 4,096 pixels a side, 8 MB at most. Its central square is kept. */
            'photo' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:'.ProfilePhotos::MAX_KILOBYTES,
                sprintf(
                    'dimensions:min_width=%d,min_height=%d,max_width=%d,max_height=%d',
                    ProfilePhotos::MIN_SIDE, ProfilePhotos::MIN_SIDE, ProfilePhotos::MAX_SIDE, ProfilePhotos::MAX_SIDE,
                ),
            ],
        ];
    }
}
