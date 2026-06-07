<?php

return [

    'name'     => env('APP_NAME', 'ZKTeco Attendance Sync'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale'   => 'en',
    'key'      => env('APP_KEY'),
    'cipher'   => 'AES-256-CBC',

    'providers' => [
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Log\LogServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Http\HttpServiceProvider::class,
        App\Providers\AppServiceProvider::class,
    ],

];
