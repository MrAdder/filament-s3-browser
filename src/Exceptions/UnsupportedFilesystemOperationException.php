<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Exceptions;

use RuntimeException;
use Throwable;

final class UnsupportedFilesystemOperationException extends RuntimeException
{
    public static function forOperation(
        string $operation,
        string $disk,
        ?string $path = null,
        ?Throwable $previous = null,
    ): self {
        $context = $path !== null ? sprintf(' [%s]', $path) : '';

        return new self(
            sprintf('The [%s] operation is not supported on disk [%s]%s.', $operation, $disk, $context),
            previous: $previous,
        );
    }
}
