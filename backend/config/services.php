<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend PWA
    |--------------------------------------------------------------------------
    |
    | URL base de la PWA para construir los deep links del código QR.
    | En pruebas: http://localhost:5173 (o el puerto del frontend).
    |
    */

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fotos de bienes
    |--------------------------------------------------------------------------
    |
    | 'disk'   = disco de Storage donde se guardan (local | google).
    | 'carpeta' = carpeta (ruta) dentro del disco para las fotos.
    |
    | Con 'local' (dev/tests) los archivos caen en storage/app/private.
    | Con 'google' se usan las credenciales GOOGLE_DRIVE_* del disco
    | 'google' definido en config/filesystems.php.
    |
    */

    'fotos' => [
        'disk' => env('PHOTOS_DISK', 'local'),
        'carpeta' => env('PHOTOS_FOLDER', 'bienes'),
    ],

];
