
<?php

return [
    'connection' => 'legacy',

    'key' => env('LEGACY_APP_KEY'),

    'cipher' => env(
        'LEGACY_APP_CIPHER',
        'AES-256-CBC'
    ),
];
