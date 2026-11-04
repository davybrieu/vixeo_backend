<?php

namespace App\Services\Tmdb;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/** TMDb is down, rate limiting us, or not configured: answered as a 503. */
class TmdbUnavailableException extends HttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(503, 'The metadata service is temporarily unavailable.', $previous);
    }
}
