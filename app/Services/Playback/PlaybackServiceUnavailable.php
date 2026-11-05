<?php

namespace App\Services\Playback;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/** TheIntroDB or OpenSubtitles is down, out of quota or not configured: answered as a 503. */
class PlaybackServiceUnavailable extends HttpException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(503, $message, $previous);
    }
}
