<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Exception;

use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;
use Mougrim\YamlCst\YamlTreeSitterCore;
use RuntimeException;

/**
 * Thrown when the tree-sitter C library returns an unexpected result or when
 * the required native libraries / FFI configuration are not available.
 *
 * This covers two categories of failures:
 * - **Setup errors** (thrown by {@see YamlTreeSitterCoreFactory}):
 *   missing `.so` files, `ffi.enable` not set.
 * - **Runtime errors** (thrown by {@see YamlTreeSitterCore}):
 *   `ts_parser_new()` or `ts_parser_parse_string()` returning NULL.
 *
 * Like all library exceptions it implements {@see YamlCstExceptionInterface},
 * so a single `catch (YamlCstExceptionInterface $e)` is sufficient.
 */
class YamlTreeSitterException extends RuntimeException implements YamlCstExceptionInterface
{
}
