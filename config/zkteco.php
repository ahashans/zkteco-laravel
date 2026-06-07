<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZKTeco Device Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for connecting to the ZKTeco MB20VL biometric attendance device
    | over UDP. The device must be reachable on your network.
    |
    */

    'device' => [
        'ip'      => env('ZKTECO_IP', '192.168.1.201'),
        'port'    => (int) env('ZKTECO_PORT', 4370),
        'timeout' => (int) env('ZKTECO_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | The endpoint and credentials used to POST the attendance log payload.
    | The API key is sent as a Bearer token in the Authorization header.
    |
    */

    'api' => [
        'url'     => env('API_URL'),
        'key'     => env('API_KEY'),
        'timeout' => (int) env('API_TIMEOUT', 30),
    ],

];
