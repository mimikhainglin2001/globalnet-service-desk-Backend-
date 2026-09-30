<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sla:check')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping();

Schedule::command('idempotency:purge')->daily();
