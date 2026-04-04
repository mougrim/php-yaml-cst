<?php

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Mougrim\YamlCst\Dto;

use FFI\CData;

/**
 * Opaque handle wrapping a TSNode value.
 *
 * @internal Used only by YamlTreeSitterCore
 */
readonly class YamlTreeSitterNodeHandle
{
    public function __construct(
        public CData $data,
    ) {
    }
}
