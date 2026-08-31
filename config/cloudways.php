<?php

$version = env('CLOUDWAYS_API_VERSION', 'v2') === 'v1' ? 'v1' : 'v2';

return [
    'access_token' => env('CLOUDWAYS_ACCESS_TOKEN'),
    'api_version' => $version,
    'base_url' => env('CLOUDWAYS_API_BASE', 'https://api.cloudways.com/api/'.$version),
];
