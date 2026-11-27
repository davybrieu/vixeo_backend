<?php

use Illuminate\Support\Facades\Mail;

it('registers the Resend transport for transactional mail', function () {
    config()->set('services.resend.key', 're_test_api_key');

    expect(config('mail.mailers.resend.transport'))->toBe('resend')
        ->and(config('services.resend'))->toHaveKey('key');

    expect(Mail::mailer('resend'))->not->toBeNull();
});
