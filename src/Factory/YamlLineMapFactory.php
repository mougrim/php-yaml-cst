<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Factory;

use Mougrim\YamlCst\DomainModel\YamlLineMap;

use function strpos;

/**
 * @internal Creates a {@see YamlLineMap} from YAML source. Not part of the public API.
 */
readonly class YamlLineMapFactory
{
    public function create(string $source): YamlLineMap
    {
        return new YamlLineMap($this->buildLineStarts($source));
    }

    /**
     * Builds the line-start array by scanning for `\n` characters.
     *
     * Note: only `\n` is treated as a line separator. For CRLF sources (`\r\n`), the `\r`
     * is counted as part of the preceding line's byte column. This is intentional — the map
     * operates on raw byte offsets, matching how tree-sitter reports positions.
     *
     * @return list<int>
     */
    private function buildLineStarts(string $source): array
    {
        $lineStarts = [0];
        $offset = 0;
        while (($pos = strpos($source, "\n", $offset)) !== false) {
            $lineStarts[] = $pos + 1;
            $offset = $pos + 1;
        }

        return $lineStarts;
    }
}
