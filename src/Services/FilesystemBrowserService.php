<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MrAdder\FilamentS3Browser\Data\BrowserBreadcrumb;
use MrAdder\FilamentS3Browser\Data\BrowserEntry;
use MrAdder\FilamentS3Browser\Data\BrowserListing;
use MrAdder\FilamentS3Browser\Exceptions\InvalidPathException;
use MrAdder\FilamentS3Browser\Exceptions\UnsupportedFilesystemOperationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class FilesystemBrowserService
{
    public function list(
        string $disk,
        string $path = '',
        ?string $search = null,
        string $sortBy = 'name',
        string $sortDirection = 'asc',
    ): BrowserListing {
        $relativePath = $this->normalizePath($path);
        $rootPrefix = $this->resolveRootPrefix($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);
        $filesystem = $this->filesystem($disk);

        $entries = [];

        foreach ($filesystem->directories($scopedPath) as $directoryPath) {
            $entries[] = $this->makeDirectoryEntry($disk, $directoryPath, $rootPrefix);
        }

        foreach ($filesystem->files($scopedPath) as $filePath) {
            $entries[] = $this->makeFileEntry($disk, $filePath, $rootPrefix, $filesystem);
        }

        if ($search !== null && $search !== '') {
            $needle = Str::lower($search);

            $entries = array_values(array_filter(
                $entries,
                static fn (BrowserEntry $entry): bool => str_contains(Str::lower($entry->name), $needle),
            ));
        }

        usort(
            $entries,
            fn (BrowserEntry $left, BrowserEntry $right): int => $this->compareEntries(
                $left,
                $right,
                $sortBy,
                $sortDirection,
            ),
        );

        return new BrowserListing(
            disk: $disk,
            path: $relativePath,
            rootPrefix: $rootPrefix,
            entries: $entries,
            breadcrumbs: $this->buildBreadcrumbs($relativePath),
        );
    }

    public function makeDirectory(string $disk, string $path): void
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be created');
        }

        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        $this->ensurePathDoesNotExist($filesystem, $scopedPath, $relativePath);

        $filesystem->makeDirectory($scopedPath);
    }

    public function upload(
        string $disk,
        string $directory,
        UploadedFile $file,
        ?string $filename = null,
    ): BrowserEntry {
        $relativeDirectory = $this->normalizePath($directory);
        $resolvedFilename = $this->sanitizePathSegment($filename ?? $file->getClientOriginalName(), 'file name');
        $relativePath = $this->joinPaths($relativeDirectory, $resolvedFilename);
        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        $this->ensurePathDoesNotExist($filesystem, $scopedPath, $relativePath);

        $filesystem->putFileAs(
            $this->scopedPath($disk, $relativeDirectory),
            $file,
            $resolvedFilename,
        );

        return $this->makeFileEntry($disk, $scopedPath, $this->resolveRootPrefix($disk), $filesystem);
    }

    public function rename(string $disk, string $path, string $newName): string
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be renamed');
        }

        $newRelativePath = $this->joinPaths(
            $this->dirname($relativePath),
            $this->sanitizePathSegment($newName, 'name'),
        );

        if ($newRelativePath === $relativePath) {
            return $relativePath;
        }

        $filesystem = $this->filesystem($disk);
        $scopedSource = $this->scopedPath($disk, $relativePath);
        $scopedTarget = $this->scopedPath($disk, $newRelativePath);

        $this->ensurePathExists($filesystem, $scopedSource, $relativePath);
        $this->ensurePathDoesNotExist($filesystem, $scopedTarget, $newRelativePath);

        $filesystem->move($scopedSource, $scopedTarget);

        return $newRelativePath;
    }

    public function move(string $disk, string $path, string $destinationDirectory): string
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be moved');
        }

        $relativeDestination = $this->normalizePath($destinationDirectory);
        $filesystem = $this->filesystem($disk);
        $scopedSource = $this->scopedPath($disk, $relativePath);

        $this->ensurePathExists($filesystem, $scopedSource, $relativePath);

        if (
            $filesystem->directoryExists($scopedSource)
            && ($relativeDestination === $relativePath || str_starts_with($relativeDestination, $relativePath.'/'))
        ) {
            throw InvalidPathException::because($destinationDirectory, 'A directory cannot be moved into itself');
        }

        $newRelativePath = $this->joinPaths($relativeDestination, $this->basename($relativePath));

        if ($newRelativePath === $relativePath) {
            return $relativePath;
        }

        $scopedTarget = $this->scopedPath($disk, $newRelativePath);

        $this->ensurePathDoesNotExist($filesystem, $scopedTarget, $newRelativePath);

        $filesystem->move($scopedSource, $scopedTarget);

        return $newRelativePath;
    }

    public function delete(string $disk, string $path): bool
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be deleted');
        }

        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        if (! $filesystem->fileExists($scopedPath)) {
            throw new FileNotFoundException(sprintf('Unable to find file [%s] on disk [%s].', $relativePath, $disk));
        }

        return $filesystem->delete($scopedPath);
    }

    public function deleteDirectory(string $disk, string $path): bool
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be deleted');
        }

        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        if (! $filesystem->directoryExists($scopedPath)) {
            throw new FileNotFoundException(sprintf('Unable to find directory [%s] on disk [%s].', $relativePath, $disk));
        }

        return $filesystem->deleteDirectory($scopedPath);
    }

    /**
     * @return array{
     *     disk: string,
     *     path: string,
     *     name: string,
     *     type: 'directory'|'file',
     *     size: ?int,
     *     last_modified: ?CarbonImmutable,
     *     mime_type: ?string,
     *     visibility: ?string,
     *     public_url: ?string,
     *     temporary_url: ?string
     * }
     */
    public function metadata(string $disk, string $path): array
    {
        $relativePath = $this->normalizePath($path);
        $scopedPath = $this->scopedPath($disk, $relativePath);
        $filesystem = $this->filesystem($disk);
        $isDirectory = $relativePath === '' || $filesystem->directoryExists($scopedPath);
        $isFile = $relativePath !== '' && $filesystem->fileExists($scopedPath);

        if (! $isDirectory && ! $isFile) {
            throw new FileNotFoundException(sprintf('Unable to find [%s] on disk [%s].', $relativePath, $disk));
        }

        return [
            'disk' => $disk,
            'path' => $relativePath,
            'name' => $relativePath === '' ? '/' : $this->basename($relativePath),
            'type' => $isDirectory ? 'directory' : 'file',
            'size' => $isFile ? $this->safeSize($filesystem, $scopedPath) : null,
            'last_modified' => $isFile ? $this->safeLastModified($filesystem, $scopedPath) : null,
            'mime_type' => $isFile ? $this->safeMimeType($filesystem, $scopedPath) : null,
            'visibility' => $relativePath === '' ? null : $this->visibility($disk, $relativePath),
            'public_url' => $isFile ? $this->publicUrl($disk, $relativePath) : null,
            'temporary_url' => $isFile ? $this->temporaryUrl($disk, $relativePath) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function temporaryUrl(
        string $disk,
        string $path,
        DateTimeInterface|int|null $expiration = null,
        array $options = [],
    ): ?string {
        if (! $this->temporaryUrlsEnabled($disk)) {
            return null;
        }

        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            return null;
        }

        $expiresAt = $expiration instanceof DateTimeInterface
            ? $expiration
            : now()->addMinutes($expiration ?? (int) config('filament-s3-browser.temporary_urls.ttl', 5));

        try {
            return $this->filesystem($disk)->temporaryUrl(
                $this->scopedPath($disk, $relativePath),
                $expiresAt,
                $options,
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function publicUrl(string $disk, string $path): ?string
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            return null;
        }

        try {
            return $this->filesystem($disk)->url($this->scopedPath($disk, $relativePath));
        } catch (Throwable) {
            return null;
        }
    }

    public function read(string $disk, string $path): string
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be read as a file');
        }

        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        if (! $filesystem->fileExists($scopedPath)) {
            throw new FileNotFoundException(sprintf('Unable to find file [%s] on disk [%s].', $relativePath, $disk));
        }

        $contents = $filesystem->get($scopedPath);

        if ($contents === null) {
            throw new FileNotFoundException(sprintf('Unable to read file [%s] on disk [%s].', $relativePath, $disk));
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function previewResponse(string $disk, string $path, ?string $name = null, array $headers = []): StreamedResponse
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be previewed');
        }

        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        if (! $filesystem->fileExists($scopedPath)) {
            throw new FileNotFoundException(sprintf('Unable to find file [%s] on disk [%s].', $relativePath, $disk));
        }

        return $filesystem->response(
            $scopedPath,
            $name ?? $this->basename($relativePath),
            $headers,
        );
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function downloadResponse(string $disk, string $path, ?string $name = null, array $headers = []): StreamedResponse
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory cannot be downloaded');
        }

        $filesystem = $this->filesystem($disk);
        $scopedPath = $this->scopedPath($disk, $relativePath);

        if (! $filesystem->fileExists($scopedPath)) {
            throw new FileNotFoundException(sprintf('Unable to find file [%s] on disk [%s].', $relativePath, $disk));
        }

        return $filesystem->download(
            $scopedPath,
            $name ?? $this->basename($relativePath),
            $headers,
        );
    }

    public function visibility(string $disk, string $path): ?string
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            return null;
        }

        try {
            return $this->filesystem($disk)->visibility($this->scopedPath($disk, $relativePath));
        } catch (Throwable) {
            return null;
        }
    }

    public function setVisibility(string $disk, string $path, string $visibility): void
    {
        $relativePath = $this->normalizePath($path);

        if ($relativePath === '') {
            throw InvalidPathException::because($path, 'The root directory visibility cannot be changed');
        }

        try {
            $this->filesystem($disk)->setVisibility($this->scopedPath($disk, $relativePath), $visibility);
        } catch (Throwable $exception) {
            throw UnsupportedFilesystemOperationException::forOperation(
                operation: 'setVisibility',
                disk: $disk,
                path: $relativePath,
                previous: $exception,
            );
        }
    }

    public function normalizePath(string $path): string
    {
        $decodedPath = $path;

        for ($index = 0; $index < 3; $index++) {
            $decodedCandidate = rawurldecode($decodedPath);

            if ($decodedCandidate === $decodedPath) {
                break;
            }

            $decodedPath = $decodedCandidate;
        }

        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $decodedPath)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw InvalidPathException::because($path, 'Path traversal is not allowed');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    public function joinPaths(string ...$paths): string
    {
        $filteredPaths = array_values(array_filter(
            $paths,
            static fn (string $path): bool => $path !== '',
        ));

        if ($filteredPaths === []) {
            return '';
        }

        return $this->normalizePath(implode('/', $filteredPaths));
    }

    public function basename(string $path): string
    {
        $normalizedPath = $this->normalizePath($path);

        if ($normalizedPath === '') {
            return '';
        }

        $segments = explode('/', $normalizedPath);

        return (string) end($segments);
    }

    public function dirname(string $path): string
    {
        $normalizedPath = $this->normalizePath($path);

        if ($normalizedPath === '' || ! str_contains($normalizedPath, '/')) {
            return '';
        }

        $segments = explode('/', $normalizedPath);
        array_pop($segments);

        return implode('/', $segments);
    }

    public function ensureWithinRootPrefix(string $path, string $rootPrefix = ''): string
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedRootPrefix = $this->normalizePath($rootPrefix);

        if ($normalizedRootPrefix === '') {
            return $normalizedPath;
        }

        if (
            $normalizedPath === $normalizedRootPrefix
            || str_starts_with($normalizedPath, $normalizedRootPrefix.'/')
        ) {
            return $normalizedPath;
        }

        throw InvalidPathException::because($path, 'The path escapes the configured root prefix');
    }

    private function filesystem(string $disk): FilesystemAdapter
    {
        $this->assertDiskIsConfigured($disk);

        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        return $filesystem;
    }

    private function scopedPath(string $disk, string $relativePath): string
    {
        return $this->ensureWithinRootPrefix(
            $this->joinPaths($this->resolveRootPrefix($disk), $this->normalizePath($relativePath)),
            $this->resolveRootPrefix($disk),
        );
    }

    private function resolveRootPrefix(string $disk): string
    {
        return $this->normalizePath($this->diskConfiguration($disk)['root'] ?? '');
    }

    /**
     * @return array{label?: string, root?: string, temporary_urls?: bool}
     */
    private function diskConfiguration(string $disk): array
    {
        $configuredDisks = config('filament-s3-browser.disks', []);

        if (is_array($configuredDisks) && $configuredDisks !== []) {
            if (! array_key_exists($disk, $configuredDisks)) {
                throw InvalidPathException::because($disk, 'The disk is not configured for the browser');
            }

            /** @var array{label?: string, root?: string, temporary_urls?: bool} $diskConfiguration */
            $diskConfiguration = $configuredDisks[$disk];

            return $diskConfiguration;
        }

        return [];
    }

    private function assertDiskIsConfigured(string $disk): void
    {
        $filesystemDisks = config('filesystems.disks', []);

        if (! is_array($filesystemDisks) || ! array_key_exists($disk, $filesystemDisks)) {
            throw InvalidPathException::because($disk, 'The disk does not exist in filesystems.php');
        }

        $this->diskConfiguration($disk);
    }

    private function relativePath(string $scopedPath, string $rootPrefix): string
    {
        $normalizedScopedPath = $this->normalizePath($scopedPath);
        $normalizedRootPrefix = $this->normalizePath($rootPrefix);

        if ($normalizedRootPrefix === '') {
            return $normalizedScopedPath;
        }

        if ($normalizedScopedPath === $normalizedRootPrefix) {
            return '';
        }

        return (string) Str::after($normalizedScopedPath, $normalizedRootPrefix.'/');
    }

    private function makeDirectoryEntry(string $disk, string $directoryPath, string $rootPrefix): BrowserEntry
    {
        $relativePath = $this->relativePath($directoryPath, $rootPrefix);

        return new BrowserEntry(
            disk: $disk,
            path: $relativePath,
            name: $this->basename($relativePath),
            isDirectory: true,
        );
    }

    private function makeFileEntry(
        string $disk,
        string $filePath,
        string $rootPrefix,
        FilesystemAdapter $filesystem,
    ): BrowserEntry {
        $relativePath = $this->relativePath($filePath, $rootPrefix);

        return new BrowserEntry(
            disk: $disk,
            path: $relativePath,
            name: $this->basename($relativePath),
            isDirectory: false,
            size: $this->safeSize($filesystem, $filePath),
            lastModified: $this->safeLastModified($filesystem, $filePath),
            mimeType: $this->safeMimeType($filesystem, $filePath),
            visibility: $this->safeVisibility($filesystem, $filePath),
        );
    }

    /**
     * @return list<BrowserBreadcrumb>
     */
    private function buildBreadcrumbs(string $path): array
    {
        $breadcrumbs = [
            new BrowserBreadcrumb(label: 'Root', path: ''),
        ];

        if ($path === '') {
            return $breadcrumbs;
        }

        $segments = explode('/', $path);
        $currentPath = '';

        foreach ($segments as $segment) {
            $currentPath = $this->joinPaths($currentPath, $segment);

            $breadcrumbs[] = new BrowserBreadcrumb(
                label: $segment,
                path: $currentPath,
            );
        }

        return $breadcrumbs;
    }

    private function compareEntries(
        BrowserEntry $left,
        BrowserEntry $right,
        string $sortBy,
        string $sortDirection,
    ): int {
        if ($left->isDirectory !== $right->isDirectory) {
            return $left->isDirectory ? -1 : 1;
        }

        if ($left->isDirectory && $right->isDirectory) {
            return strcasecmp($left->name, $right->name);
        }

        $direction = $sortDirection === 'desc' ? -1 : 1;

        return $direction * match ($sortBy) {
            'last_modified' => $this->compareNullableIntegers(
                $left->lastModified?->getTimestamp(),
                $right->lastModified?->getTimestamp(),
                $left->name,
                $right->name,
            ),
            'size' => $this->compareNullableIntegers(
                $left->size,
                $right->size,
                $left->name,
                $right->name,
            ),
            default => strcasecmp($left->name, $right->name),
        };
    }

    private function compareNullableIntegers(
        ?int $left,
        ?int $right,
        string $leftFallback,
        string $rightFallback,
    ): int {
        if ($left === $right) {
            return strcasecmp($leftFallback, $rightFallback);
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }

    private function sanitizePathSegment(string $value, string $description): string
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidPathException::because($value, sprintf('The %s cannot be empty', $description));
        }

        $normalizedValue = $this->normalizePath($value);

        if ($normalizedValue === '' || str_contains($normalizedValue, '/')) {
            throw InvalidPathException::because($value, sprintf('The %s must be a single path segment', $description));
        }

        return $normalizedValue;
    }

    private function safeSize(FilesystemAdapter $filesystem, string $path): ?int
    {
        try {
            return $filesystem->size($path);
        } catch (Throwable) {
            return null;
        }
    }

    private function safeLastModified(FilesystemAdapter $filesystem, string $path): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromTimestampUTC($filesystem->lastModified($path));
        } catch (Throwable) {
            return null;
        }
    }

    private function safeMimeType(FilesystemAdapter $filesystem, string $path): ?string
    {
        try {
            $mimeType = $filesystem->mimeType($path);

            return $mimeType === false ? null : $mimeType;
        } catch (Throwable) {
            return null;
        }
    }

    private function safeVisibility(FilesystemAdapter $filesystem, string $path): ?string
    {
        try {
            return $filesystem->visibility($path);
        } catch (Throwable) {
            return null;
        }
    }

    private function temporaryUrlsEnabled(string $disk): bool
    {
        $diskConfiguration = $this->diskConfiguration($disk);

        return (bool) ($diskConfiguration['temporary_urls'] ?? config('filament-s3-browser.temporary_urls.enabled', true));
    }

    private function pathExists(FilesystemAdapter $filesystem, string $path): bool
    {
        return $filesystem->fileExists($path) || $filesystem->directoryExists($path);
    }

    private function ensurePathExists(FilesystemAdapter $filesystem, string $path, string $displayPath): void
    {
        if ($this->pathExists($filesystem, $path)) {
            return;
        }

        throw new FileNotFoundException(sprintf('Unable to find [%s].', $displayPath));
    }

    private function ensurePathDoesNotExist(FilesystemAdapter $filesystem, string $path, string $displayPath): void
    {
        if (! $this->pathExists($filesystem, $path)) {
            return;
        }

        throw InvalidPathException::because($displayPath, 'An item already exists at this path');
    }
}
