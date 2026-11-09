<?php

namespace App\Services\Tmdb;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * TMDb no longer accepts the profile's session (access removed on themoviedb.org): the link is
 * deleted, the person links the account again. Also used when no account is linked.
 */
class TmdbLinkRevoked extends HttpException
{
    public static function revoked(): self
    {
        return new self(409, 'Your TMDb account is no longer linked to this profile: link it again.');
    }

    public static function notLinked(): self
    {
        return new self(409, 'Link a TMDb account to this profile first.');
    }
}
