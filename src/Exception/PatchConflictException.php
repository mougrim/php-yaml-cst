<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Exception;

use Mougrim\YamlCst\DomainModel\YamlSpan;
use RuntimeException;

class PatchConflictException extends RuntimeException implements YamlCstExceptionInterface
{
    public function __construct(
        public readonly YamlSpan $previousSpan,
        public readonly YamlSpan $currentSpan,
        int $index,
    ) {
        parent::__construct(
            "Overlapping patches at index {$index}: previous span bytes [{$previousSpan->startByte}, {$previousSpan->endByte}), current span bytes [{$currentSpan->startByte}, {$currentSpan->endByte})",
        );
    }
}
