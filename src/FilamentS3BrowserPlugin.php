<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser;

use Filament\Contracts\Plugin;
use Filament\Panel;
use MrAdder\FilamentS3Browser\Filament\Pages\S3BrowserPage;

final class FilamentS3BrowserPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'filament-s3-browser';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            S3BrowserPage::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
