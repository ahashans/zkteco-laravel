<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        App\Console\Commands\FetchAttendanceLogs::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
