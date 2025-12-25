<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backups:run-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
