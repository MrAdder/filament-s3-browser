<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Data;

use Filament\Support\Enums\Width;

final readonly class FilePreview
{
    public const MODE_IMAGE = 'image';

    public const MODE_PDF = 'pdf';

    public const MODE_TEXT = 'text';

    public const MODE_METADATA = 'metadata';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $mode,
        public string $disk,
        public string $path,
        public string $name,
        public ?string $mimeType,
        public ?int $size,
        public ?string $url = null,
        public ?string $textContent = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    public function opensInNewTab(): bool
    {
        return $this->mode === self::MODE_PDF && $this->url !== null;
    }

    public function modalWidth(): Width
    {
        return match ($this->mode) {
            self::MODE_IMAGE => Width::SevenExtraLarge,
            self::MODE_TEXT => Width::FiveExtraLarge,
            default => Width::ThreeExtraLarge,
        };
    }

    public function heading(): string
    {
        return match ($this->mode) {
            self::MODE_IMAGE => sprintf('Image Preview: %s', $this->name),
            self::MODE_PDF => sprintf('PDF Preview: %s', $this->name),
            self::MODE_TEXT => sprintf('Text Preview: %s', $this->name),
            default => sprintf('Preview Details: %s', $this->name),
        };
    }
}
