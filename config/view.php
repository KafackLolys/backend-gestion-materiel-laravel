<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env('VERCEL')
        ? sys_get_temp_dir().'/storage/framework/views'
        : storage_path('framework/views'),
];
