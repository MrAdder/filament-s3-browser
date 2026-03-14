<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use MrAdder\FilamentS3Browser\Data\FilePreview;
use MrAdder\FilamentS3Browser\Services\FilePreviewService;

beforeEach(function (): void {
    Storage::fake('s3');
});

it('previews images in a modal using a signed internal url', function (): void {
    $service = app(FilePreviewService::class);

    Storage::disk('s3')->put('browser/gallery/photo.jpg', 'fake-image');

    $preview = $service->preview('s3', 'gallery/photo.jpg', 'photo.jpg', 'image/jpeg', 10);

    expect($preview->mode)->toBe(FilePreview::MODE_IMAGE)
        ->and($preview->url)->toContain('filament-s3-browser/preview');
});

it('opens pdf previews in a new tab', function (): void {
    $service = app(FilePreviewService::class);

    Storage::disk('s3')->put('browser/docs/guide.pdf', 'fake-pdf');

    $preview = $service->preview('s3', 'docs/guide.pdf', 'guide.pdf', 'application/pdf', 10);

    expect($preview->mode)->toBe(FilePreview::MODE_PDF)
        ->and($preview->opensInNewTab())->toBeTrue();
});

it('previews small text files inline', function (): void {
    $service = app(FilePreviewService::class);

    Storage::disk('s3')->put('browser/logs/app.log', "line 1\nline 2");

    $preview = $service->preview('s3', 'logs/app.log', 'app.log', 'text/plain', 14);

    expect($preview->mode)->toBe(FilePreview::MODE_TEXT)
        ->and($preview->textContent)->toContain('line 1');
});

it('falls back to metadata for large text files', function (): void {
    $service = app(FilePreviewService::class);

    config()->set('filament-s3-browser.preview.text_limit_bytes', 10);
    Storage::disk('s3')->put('browser/logs/app.log', str_repeat('a', 20));

    $preview = $service->preview('s3', 'logs/app.log', 'app.log', 'text/plain', 20);

    expect($preview->mode)->toBe(FilePreview::MODE_METADATA)
        ->and($preview->message)->toContain('too large');
});

it('shows metadata only for binary files', function (): void {
    $service = app(FilePreviewService::class);

    Storage::disk('s3')->put('browser/backups/archive.zip', 'zip-body');

    $preview = $service->preview('s3', 'backups/archive.zip', 'archive.zip', 'application/zip', 8);

    expect($preview->mode)->toBe(FilePreview::MODE_METADATA)
        ->and($preview->message)->toContain('Binary files');
});
