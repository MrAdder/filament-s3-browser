<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Data;

final readonly class BrowserBreadcrumb
{
    public function __construct(
        public string $label,
        public string $path,
    ) {}
}
