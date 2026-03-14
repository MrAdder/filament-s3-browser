<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Data;

use Carbon\CarbonImmutable;

final readonly class BrowserEntry
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $name,
        public bool $isDirectory,
        public ?int $size = null,
        public ?CarbonImmutable $lastModified = null,
        public ?string $mimeType = null,
        public ?string $visibility = null,
    ) {}

    public function type(): string
    {
        return $this->isDirectory ? 'directory' : 'file';
    }

    public function extension(): ?string
    {
        if ($this->isDirectory) {
            return null;
        }

        $extension = pathinfo($this->name, PATHINFO_EXTENSION);

        return $extension === '' ? null : strtolower($extension);
    }
}
