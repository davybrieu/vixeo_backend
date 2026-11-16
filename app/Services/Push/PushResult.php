<?php

namespace App\Services\Push;

/** What FCM answered for one device. */
enum PushResult: string
{
    case Sent = 'sent';
    /** The app was uninstalled or its token replaced: the device is forgotten. */
    case Invalid = 'invalid';
    /** Network, quota, FCM unavailable: try again later. */
    case Failed = 'failed';
}
