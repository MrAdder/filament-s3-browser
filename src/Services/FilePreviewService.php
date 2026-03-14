<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Services;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use MrAdder\FilamentS3Browser\Data\FilePreview;

final class FilePreviewService
{
    public function __construct(
        private readonly FilesystemBrowserService $browser,
    ) {}

    public function modeFor(string $name, ?string $mimeType = null): string
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $normalizedMimeType = $mimeType !== null ? Str::lower($mimeType) : null;

        if (
            $normalizedMimeType !== null
            && str_starts_with($normalizedMimeType, 'image/')
        ) {
            return FilePreview::MODE_IMAGE;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'avif'], true)) {
            return FilePreview::MODE_IMAGE;
        }

        if ($normalizedMimeType === 'application/pdf' || $extension === 'pdf') {
            return FilePreview::MODE_PDF;
        }

        if ($this->isTextMimeType($normalizedMimeType) || in_array($extension, $this->textExtensions(), true)) {
            return FilePreview::MODE_TEXT;
        }

        return FilePreview::MODE_METADATA;
    }

    public function previewUrl(string $disk, string $path): string
    {
        return URL::temporarySignedRoute(
            'filament-s3-browser.preview',
            now()->addMinutes((int) config('filament-s3-browser.preview.signed_url_ttl', 5)),
            [
                'disk' => $disk,
                'path' => $this->browser->normalizePath($path),
            ],
        );
    }

    public function preview(
        string $disk,
        string $path,
        ?string $name = null,
        ?string $mimeType = null,
        ?int $size = null,
    ): FilePreview {
        $normalizedPath = $this->browser->normalizePath($path);
        $metadata = $this->browser->metadata($disk, $normalizedPath);
        $resolvedName = $name ?? (string) $metadata['name'];
        $resolvedMimeType = $mimeType ?? $metadata['mime_type'];
        $resolvedSize = $size ?? $metadata['size'];
        $mode = $this->modeFor($resolvedName, $resolvedMimeType);

        if ($mode === FilePreview::MODE_IMAGE) {
            return new FilePreview(
                mode: FilePreview::MODE_IMAGE,
                disk: $disk,
                path: $normalizedPath,
                name: $resolvedName,
                mimeType: $resolvedMimeType,
                size: $resolvedSize,
                url: $this->previewUrl($disk, $normalizedPath),
                metadata: $metadata,
            );
        }

        if ($mode === FilePreview::MODE_PDF) {
            return new FilePreview(
                mode: FilePreview::MODE_PDF,
                disk: $disk,
                path: $normalizedPath,
                name: $resolvedName,
                mimeType: $resolvedMimeType,
                size: $resolvedSize,
                url: $this->previewUrl($disk, $normalizedPath),
                metadata: $metadata,
            );
        }

        if ($mode === FilePreview::MODE_TEXT) {
            $textLimit = (int) config('filament-s3-browser.preview.text_limit_bytes', 131072);

            if ($resolvedSize !== null && $resolvedSize > $textLimit) {
                return new FilePreview(
                    mode: FilePreview::MODE_METADATA,
                    disk: $disk,
                    path: $normalizedPath,
                    name: $resolvedName,
                    mimeType: $resolvedMimeType,
                    size: $resolvedSize,
                    message: sprintf(
                        'This text file is too large to preview inline. The configured limit is %s bytes.',
                        number_format($textLimit),
                    ),
                    metadata: $metadata,
                );
            }

            return new FilePreview(
                mode: FilePreview::MODE_TEXT,
                disk: $disk,
                path: $normalizedPath,
                name: $resolvedName,
                mimeType: $resolvedMimeType,
                size: $resolvedSize,
                textContent: $this->browser->read($disk, $normalizedPath),
                metadata: $metadata,
            );
        }

        return new FilePreview(
            mode: FilePreview::MODE_METADATA,
            disk: $disk,
            path: $normalizedPath,
            name: $resolvedName,
            mimeType: $resolvedMimeType,
            size: $resolvedSize,
            message: 'Binary files are not previewed inline. Metadata is shown instead.',
            metadata: $metadata,
        );
    }

    private function isTextMimeType(?string $mimeType): bool
    {
        if ($mimeType === null) {
            return false;
        }

        return str_starts_with($mimeType, 'text/')
            || in_array($mimeType, [
                'application/json',
                'application/ld+json',
                'application/xml',
                'application/x-yaml',
                'application/yaml',
                'application/javascript',
                'application/x-httpd-php',
            ], true);
    }

    /**
     * @return list<string>
     */
    private function textExtensions(): array
    {
        return [
            'csv',
            'css',
            'env',
            'html',
            'ini',
            'js',
            'json',
            'log',
            'md',
            'php',
            'sql',
            'svg',
            'ts',
            'txt',
            'xml',
            'yaml',
            'yml',
        ];
    }
}
