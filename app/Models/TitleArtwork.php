<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The poster, backdrop and logo a profile chose for a title (App\Services\TitleArtworks); null:
 * TMDb's choice.
 */
#[Fillable(['profile_id', 'media_type', 'tmdb_id', 'poster_path', 'backdrop_path', 'logo_path', 'logo_aspect_ratio'])]
class TitleArtwork extends Model
{
    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'logo_aspect_ratio' => 'float',
        ];
    }
}
