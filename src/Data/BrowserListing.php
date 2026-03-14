<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Data;

final readonly class BrowserListing
{
    /**
     * @param  list<BrowserEntry>  $entries
     * @param  list<BrowserBreadcrumb>  $breadcrumbs
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $rootPrefix,
        public array $entries,
        public array $breadcrumbs,
    ) {}

    public function isRoot(): bool
    {
        return $this->path === '';
    }

    public function hasEntries(): bool
    {
        return $this->entries !== [];
    }
}
