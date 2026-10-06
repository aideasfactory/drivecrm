<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile App Store Links
    |--------------------------------------------------------------------------
    |
    | Public store listing URLs for the mobile app. Env vars override the
    | live store listings below.
    |
    */

    'apple' => env('APPLE_APP_LINK', 'https://apps.apple.com/us/app/drive/id6791224771') ?: 'https://apps.apple.com/us/app/drive/id6791224771',

    'android' => env('ANDROID_APP_LINK', 'https://play.google.com/store/apps/details?id=com.driveapp.driveapp') ?: 'https://play.google.com/store/apps/details?id=com.driveapp.driveapp',

];
