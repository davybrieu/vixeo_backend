<?php

namespace App\Http\Requests\Api\V1\Messages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/** A photo (15 MB at most) or a video (100 MB at most), as a multipart field `file`. */
class AttachmentRequest extends FormRequest
{
    /** Largest file, in kilobytes (a video). */
    public const MAX_KB = 102400;

    /** Largest photo, in kilobytes. */
    public const MAX_IMAGE_KB = 15360;

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm', 'max:'.self::MAX_KB],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $file = $this->file('file');
                if (! $file instanceof UploadedFile || $validator->errors()->has('file')) {
                    return;
                }
                $mime = (string) $file->getMimeType();
                if (! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'video/')) {
                    $validator->errors()->add('file', __('Only photos and videos can be sent.'));
                } elseif (str_starts_with($mime, 'image/') && $file->getSize() > self::MAX_IMAGE_KB * 1024) {
                    $validator->errors()->add('file', __('A photo is limited to 15 MB.'));
                }
            },
        ];
    }

    public function attachment(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }
}
