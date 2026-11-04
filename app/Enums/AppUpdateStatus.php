<?php

namespace App\Enums;

/** What an installed app should do about updates. */
enum AppUpdateStatus: string
{
    /** Latest version installed. */
    case UpToDate = 'up_to_date';
    /** A newer version exists: suggest it. */
    case UpdateAvailable = 'update_available';
    /** The installed version is older than a required release: block until updated. */
    case UpdateRequired = 'update_required';
}
