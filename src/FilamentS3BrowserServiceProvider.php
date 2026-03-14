<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser;

use MrAdder\FilamentS3Browser\Services\BrowserAuthorizationService;
use MrAdder\FilamentS3Browser\Services\FilePreviewService;
use MrAdder\FilamentS3Browser\Services\FilesystemBrowserService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentS3BrowserServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-s3-browser')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BrowserAuthorizationService::class);
        $this->app->singleton(FilePreviewService::class);
        $this->app->singleton(FilesystemBrowserService::class);
    }
}
