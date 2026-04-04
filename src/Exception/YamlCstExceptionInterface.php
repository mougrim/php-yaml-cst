<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Exception;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by this library.
 *
 * Catching this interface is sufficient to handle all yaml-cst errors:
 *
 * ```php
 * try {
 *     $document = $parser->parse($yaml, $core);
 *     $pair = $document->index->get(['database', 'host']);
 * } catch (YamlCstExceptionInterface $e) {
 *     // handles YamlSyntaxException, PathNotFoundException, PatchConflictException,
 *     // MaxNestingDepthExceededException, AbiMismatchException, and YamlTreeSitterException
 * }
 * ```
 */
interface YamlCstExceptionInterface extends Throwable
{
}
