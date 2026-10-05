<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Rede de segurança caso algum ExpireOrderJob se perca.
Schedule::command('orders:expire-stale')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::command('model:prune')->daily()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
