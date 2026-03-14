<?php

declare(strict_types=1);

return [
    'disks' => [
        /*
        's3' => [
            'label' => 'Amazon S3',
            'root' => '',
            'temporary_urls' => true,
        ],
        */
    ],

    'default_disk' => env('FILAMENT_S3_BROWSER_DEFAULT_DISK', 's3'),

    'permissions' => [
        'view' => true,
        'upload' => true,
        'rename' => true,
        'move' => true,
        'delete' => true,
        'download' => true,
        'create_directory' => true,
        'set_visibility' => true,
    ],

    'temporary_urls' => [
        'enabled' => true,
        'ttl' => 5,
    ],

    'navigation' => [
        'enabled' => true,
        'group' => 'Storage',
        'label' => 'S3 Browser',
        'icon' => 'heroicon-o-cloud',
        'sort' => 50,
    ],

    'preview' => [
        'text_limit_bytes' => 131072,
        'signed_url_ttl' => 5,
    ],

    'upload' => [
        'max_size_kb' => 51200,
    ],
];
