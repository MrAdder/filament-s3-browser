<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Exceptions;

use RuntimeException;

final class InvalidPathException extends RuntimeException
{
    public static function because(string $path, string $reason): self
    {
        return new self(sprintf('Invalid browser path [%s]: %s.', $path, $reason));
    }
}
