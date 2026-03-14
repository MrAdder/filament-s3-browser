<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Data;

final readonly class BrowserTarget
{
    public function __construct(
        public string $disk,
        public string $path,
        public bool $isDirectory = false,
    ) {}
}
