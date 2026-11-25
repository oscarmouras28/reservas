<?php

namespace App\Exceptions;

use RuntimeException;

/** La hora elegida ya no está libre. */
class SlotUnavailableException extends RuntimeException {}
