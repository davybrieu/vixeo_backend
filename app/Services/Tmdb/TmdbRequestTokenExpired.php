<?php

namespace App\Services\Tmdb;

use RuntimeException;

/** The request token expired or was already used: start the link again. */
class TmdbRequestTokenExpired extends RuntimeException {}
