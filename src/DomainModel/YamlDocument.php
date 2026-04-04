<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use Mougrim\YamlCst\YamlCstParser;

use function array_any;

/**
 * Immutable value object produced by {@see YamlCstParser::parse}.
 *
 * It bundles everything needed to inspect a YAML document.
 */
readonly class YamlDocument
{
    /**
     * @param string $source the original YAML text (never mutated)
     * @param YamlCstTree $tree the full Concrete Syntax Tree; use this to access sequences and other constructs that
     * the path-based {@see $index} does not cover
     * @param YamlIndex $index a path-based lookup map for mapping pairs (keys only, not sequences)
     * @param YamlLineMap $lineMap converts byte offsets to human-readable line/column positions
     */
    public function __construct(
        public string $source,
        public YamlCstTree $tree,
        public YamlIndex $index,
        public YamlLineMap $lineMap,
    ) {
    }

    /**
     * Returns true when the document root has no non-extra named children.
     *
     * This is the case for an empty source string or a document that contains only comments.
     * A document with sequences (but no mapping pairs) is NOT considered empty.
     */
    public function isEmpty(): bool
    {
        return !array_any($this->tree->root()->namedChildren(), static fn ($child): bool => !$child->isExtra());
    }
}
