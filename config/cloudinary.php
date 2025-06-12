<?php

use Cloudinary\Cloudinary;

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Cloudinary integration. Do not use quotation marks in the
    | .env file values. This file is used to load your Cloudinary environment.
    |
    */

    // Optional webhook URL
    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),

    // Automatically build Cloudinary URL from individual components
    'cloud_url' => env('CLOUDINARY_URL', 'cloudinary://' . env('CLOUDINARY_API_KEY') . ':' . env('CLOUDINARY_API_SECRET') . '@' . env('CLOUDINARY_CLOUD_NAME')),

    // Upload preset from Cloudinary dashboard
    'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),

    // Blade upload widget support
    'upload_route' => env('CLOUDINARY_UPLOAD_ROUTE'),

    'upload_action' => env('CLOUDINARY_UPLOAD_ACTION'),

    // Optional direct API URL
    'api_url' => env('CLOUDINARY_API_URL'),
];
