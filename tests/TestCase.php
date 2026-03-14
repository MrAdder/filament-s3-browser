<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Tests;

use MrAdder\FilamentS3Browser\FilamentS3BrowserServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public mixed $service;

    public mixed $user;

    protected function getPackageProviders($app): array
    {
        return [
            FilamentS3BrowserServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('filesystems.default', 's3');
        $app['config']->set('filesystems.disks.s3', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/s3'),
        ]);
        $app['config']->set('filament-s3-browser.default_disk', 's3');
        $app['config']->set('filament-s3-browser.disks', [
            's3' => [
                'label' => 'Test S3',
                'root' => 'browser',
                'temporary_urls' => true,
            ],
        ]);
    }
}
