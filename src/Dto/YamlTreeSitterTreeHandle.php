<?php

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Mougrim\YamlCst\Dto;

use FFI\CData;

/**
 * Opaque handle wrapping a TSTree* pointer.
 *
 * @internal Used only by YamlTreeSitterCore
 */
readonly class YamlTreeSitterTreeHandle
{
    public function __construct(
        public CData $pointer,
    ) {
    }
}
