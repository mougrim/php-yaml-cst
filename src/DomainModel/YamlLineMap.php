<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use InvalidArgumentException;

use function count;
use function intdiv;

readonly class YamlLineMap
{
    /**
     * @param list<int> $lineStarts offsets of line starts (bytes), must be sorted ascending
     *
     * @internal Constructed by {@see YamlLineMapFactory}. Passing an unsorted list will silently
     *           produce wrong results from {@see locate()} (binary search assumes sorted input).
     */
    public function __construct(
        private array $lineStarts,
    ) {
    }

    /**
     * Converts a byte offset to a {@see YamlLocation} with 1-based line and byte column.
     *
     * The column is a raw byte offset from the start of the line, not a Unicode character index.
     * For CRLF sources the `\r` at the end of each line is counted as part of the line's bytes,
     * so the column reported for bytes near a line ending will include it.
     *
     * Offsets beyond the source length are accepted — they are reported as residing on the last
     * known line with a column that exceeds the actual line length. This mirrors how tree-sitter
     * reports positions for synthetic or partially-constructed byte ranges.
     *
     * @throws InvalidArgumentException if $byteOffset is negative
     */
    public function locate(int $byteOffset): YamlLocation
    {
        if ($byteOffset < 0) {
            throw new InvalidArgumentException("byteOffset must be >= 0, got {$byteOffset}");
        }

        $low = 0;
        $high = count($this->lineStarts) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($this->lineStarts[$mid] <= $byteOffset) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }
        $line = $high; // 0-based
        $columnBytes = $byteOffset - $this->lineStarts[$line];

        return new YamlLocation($line + 1, $columnBytes + 1);
    }
}
