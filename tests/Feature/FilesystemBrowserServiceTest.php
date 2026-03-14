<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use MrAdder\FilamentS3Browser\Exceptions\InvalidPathException;
use MrAdder\FilamentS3Browser\Exceptions\UnsupportedFilesystemOperationException;
use MrAdder\FilamentS3Browser\Services\FilesystemBrowserService;

beforeEach(function (): void {
    Storage::fake('s3');
});

it('lists folders before files at the current level', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->makeDirectory('browser/contracts');
    Storage::disk('s3')->put('browser/readme.txt', 'hello');
    Storage::disk('s3')->put('browser/contracts/agreement.txt', 'signed');

    $listing = $service->list('s3');

    expect($listing->path)->toBe('')
        ->and(array_map(fn ($entry) => $entry->name, $listing->entries))->toBe([
            'contracts',
            'readme.txt',
        ]);
});

it('lists nested directories with relative breadcrumbs', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->makeDirectory('browser/contracts/2026/march');
    Storage::disk('s3')->put('browser/contracts/2026/march/report.txt', 'notes');

    $listing = $service->list('s3', 'contracts/2026');

    expect($listing->path)->toBe('contracts/2026')
        ->and(array_map(fn ($crumb) => $crumb->path, $listing->breadcrumbs))->toBe([
            '',
            'contracts',
            'contracts/2026',
        ])
        ->and(array_map(fn ($entry) => $entry->path, $listing->entries))->toBe([
            'contracts/2026/march',
        ]);
});

it('creates a folder within the configured root', function (): void {
    $service = app(FilesystemBrowserService::class);

    $service->makeDirectory('s3', 'uploads/images');

    expect(Storage::disk('s3')->directoryExists('browser/uploads/images'))->toBeTrue();
});

it('uploads a file to the target directory', function (): void {
    $service = app(FilesystemBrowserService::class);
    $file = UploadedFile::fake()->createWithContent('invoice.txt', 'invoice-body');

    $entry = $service->upload('s3', 'uploads', $file);

    expect($entry->path)->toBe('uploads/invoice.txt')
        ->and(Storage::disk('s3')->get('browser/uploads/invoice.txt'))->toBe('invoice-body');
});

it('renames a file without leaving the configured root', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->put('browser/archive/report.txt', 'report');

    $newPath = $service->rename('s3', 'archive/report.txt', 'report-final.txt');

    expect($newPath)->toBe('archive/report-final.txt')
        ->and(Storage::disk('s3')->fileExists('browser/archive/report-final.txt'))->toBeTrue()
        ->and(Storage::disk('s3')->fileExists('browser/archive/report.txt'))->toBeFalse();
});

it('moves a file into another directory', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->put('browser/source/report.txt', 'report');
    Storage::disk('s3')->makeDirectory('browser/destination');

    $newPath = $service->move('s3', 'source/report.txt', 'destination');

    expect($newPath)->toBe('destination/report.txt')
        ->and(Storage::disk('s3')->fileExists('browser/destination/report.txt'))->toBeTrue()
        ->and(Storage::disk('s3')->fileExists('browser/source/report.txt'))->toBeFalse();
});

it('deletes a file', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->put('browser/archive/delete-me.txt', 'report');

    $deleted = $service->delete('s3', 'archive/delete-me.txt');

    expect($deleted)->toBeTrue()
        ->and(Storage::disk('s3')->fileExists('browser/archive/delete-me.txt'))->toBeFalse();
});

it('restricts traversal outside the configured root prefix', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->put('outside.txt', 'forbidden');
    Storage::disk('s3')->put('browser/inside.txt', 'allowed');

    expect(fn () => $service->list('s3', '../outside.txt'))
        ->toThrow(InvalidPathException::class)
        ->and(array_map(fn ($entry) => $entry->path, $service->list('s3')->entries))
        ->toBe(['inside.txt']);
});

it('creates temporary urls when the disk supports them', function (): void {
    $service = app(FilesystemBrowserService::class);

    Storage::disk('s3')->put('browser/report.txt', 'report');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path): string => 'https://example.test/temp?path='.rawurlencode($path),
    );

    $url = $service->temporaryUrl('s3', 'report.txt');

    expect($url)->toContain('browser%2Freport.txt');
});

it('returns null when temporary urls are unsupported', function (): void {
    $service = app(FilesystemBrowserService::class);

    config()->set('filesystems.disks.unsupported', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/unsupported')]);
    config()->set('filament-s3-browser.disks.unsupported', ['label' => 'Unsupported', 'root' => 'browser']);

    /** @var MockInterface&FilesystemAdapter $filesystem */
    $filesystem = Mockery::mock(FilesystemAdapter::class);
    /** @phpstan-ignore-next-line */
    $filesystem->shouldReceive('temporaryUrl')->with(Mockery::type('string'), Mockery::type(DateTimeInterface::class), [])->andThrow(new RuntimeException('unsupported'));

    Storage::shouldReceive('disk')
        ->once()
        ->with('unsupported')
        ->andReturn($filesystem);

    expect($service->temporaryUrl('unsupported', 'report.txt'))->toBeNull();
});

it('wraps unsupported visibility changes in a package exception', function (): void {
    $service = app(FilesystemBrowserService::class);

    config()->set('filesystems.disks.mocked', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/mocked')]);
    config()->set('filament-s3-browser.disks.mocked', ['label' => 'Mocked', 'root' => 'browser']);

    /** @var MockInterface&FilesystemAdapter $filesystem */
    $filesystem = Mockery::mock(FilesystemAdapter::class);
    /** @phpstan-ignore-next-line */
    $filesystem->shouldReceive('setVisibility')->with('browser/report.txt', 'public')->andThrow(new RuntimeException('unsupported'));

    Storage::shouldReceive('disk')
        ->once()
        ->with('mocked')
        ->andReturn($filesystem);

    expect(fn () => $service->setVisibility('mocked', 'report.txt', 'public'))
        ->toThrow(UnsupportedFilesystemOperationException::class);
});
