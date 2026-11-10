<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** URL of a channel logo on our API (App\Services\Tv\ChannelLogos), for the provider's [$url]. */
function proxiedLogo(string $url): string
{
    return config('vixeo.urls.api').'/api/v1/tv/logo/'.substr(hash('sha256', $url), 0, 32);
}
