<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();
