<?php

declare(strict_types=1);

namespace App\SeatLock;

use RuntimeException;

final class LockBackendUnavailable extends RuntimeException {}
