<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

/**
 * A human-readable location inside a YAML source string.
 *
 * Both {@see $line} and {@see $col} are 1-based.
 * {@see $col} is a byte offset within the line, not a Unicode character index.
 *
 * Returned by {@see YamlLineMap::locate()}.
 */
readonly class YamlLocation
{
    /**
     * @param int $line 1-based line number
     * @param int $col  1-based byte column (byte offset from the start of the line)
     *
     * @internal constructed by {@see YamlLineMap::locate()}
     */
    public function __construct(
        public int $line,
        public int $col,
    ) {
    }
}
