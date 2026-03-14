<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use MrAdder\FilamentS3Browser\Data\BrowserTarget;
use MrAdder\FilamentS3Browser\Services\BrowserAuthorizationService;
use MrAdder\FilamentS3Browser\Tests\Support\AllowViewDenyDeleteBrowserTargetPolicy;

it('falls back to config flags when no policy exists', function (): void {
    $service = app(BrowserAuthorizationService::class);
    $user = new GenericUser(['id' => 1, 'name' => 'Tester']);

    config()->set('filament-s3-browser.permissions.upload', false);

    expect($service->canUpload($user, 's3', 'browser'))->toBeFalse()
        ->and($service->canView($user, 's3'))->toBeTrue();
});

it('uses the configured policy when one is registered', function (): void {
    $service = app(BrowserAuthorizationService::class);
    $user = new GenericUser(['id' => 1, 'name' => 'Tester']);

    Gate::policy(BrowserTarget::class, AllowViewDenyDeleteBrowserTargetPolicy::class);
    config()->set('filament-s3-browser.permissions.delete', true);

    expect($service->canView($user, 's3', 'folder/report.txt'))->toBeTrue()
        ->and($service->canDelete($user, 's3', 'folder/report.txt'))->toBeFalse()
        ->and($service->canUpload($user, 's3', 'locked'))->toBeFalse();
});

it('checks whether the current user can access at least one disk', function (): void {
    $service = app(BrowserAuthorizationService::class);
    $user = new GenericUser(['id' => 1, 'name' => 'Tester']);

    Gate::policy(BrowserTarget::class, AllowViewDenyDeleteBrowserTargetPolicy::class);

    expect($service->canAccessAnyDisk($user, [
        's3' => ['label' => 'Test S3'],
    ]))->toBeTrue()
        ->and($service->canAccessAnyDisk($user, [
            'private' => ['label' => 'Private'],
        ]))->toBeFalse();
});
