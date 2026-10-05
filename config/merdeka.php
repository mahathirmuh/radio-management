<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application url base
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'url' => env('APP_MERDEKA_URL'),
    'safe' => env('URL_SAFE'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment Audience
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */
    
    'audience' => env('MERDEKA_CLIENT_AUDIENCE'),
    /*
    |--------------------------------------------------------------------------
    | Application Environment Secret Key
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */
    'secret' => env('MERDEKA_CLIENT_SECRET_KEY'),

      

];
