<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Exception;

use RuntimeException;

class PathNotFoundException extends RuntimeException implements YamlCstExceptionInterface
{
    public function __construct(string $path)
    {
        parent::__construct("Path not found: {$path}. Use YamlIndex::find() for a non-throwing lookup.");
    }
}
