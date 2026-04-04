<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use Mougrim\YamlCst\YamlIndexBuilder;

/**
 * @internal One node in the YamlIndex trie. Not part of the public API.
 *
 * This class is intentionally mutable. Properties are written only during the build phase
 * inside {@see YamlIndexBuilder} and are effectively read-only afterwards.
 */
class YamlIndexNode
{
    public ?YamlMappingPairRef $pair = null;

    /** @var array<string, YamlIndexNode> children keyed by segment, in document order */
    public array $children = [];
}
