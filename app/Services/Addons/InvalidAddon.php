<?php

namespace App\Services\Addons;

use RuntimeException;

/**
 * A plug-in that cannot be installed or read: not a manifest, unreachable, private address…
 * The message is in French, for the person installing it.
 */
class InvalidAddon extends RuntimeException {}
