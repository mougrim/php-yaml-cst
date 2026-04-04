<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Exception;

use Mougrim\YamlCst\YamlIndexBuilder;
use RuntimeException;

/**
 * Thrown when the YAML document nesting depth exceeds the library's configured limit.
 *
 * Pathologically deeply nested YAML can cause unbounded PHP stack growth. This exception
 * is thrown by {@see YamlIndexBuilder} before the stack overflows.
 */
class MaxNestingDepthExceededException extends RuntimeException implements YamlCstExceptionInterface
{
    public function __construct(int $maxDepth)
    {
        parent::__construct(
            "YAML nesting depth exceeds the maximum allowed limit of {$maxDepth}. "
            . 'Consider restructuring the document to reduce nesting.',
        );
    }
}
