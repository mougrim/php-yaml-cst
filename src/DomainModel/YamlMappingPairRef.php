<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use function implode;
use function substr;

readonly class YamlMappingPairRef
{
    /**
     * @param list<string> $segments path segments to this pair (e.g. ['database', 'host'])
     * @param string $keyText normalized key text (quotes stripped, minimal unescape applied)
     * @param YamlCstNodeRef $pairNode the full mapping-pair node (encompasses key + colon + value)
     * @param YamlCstNodeRef $keyNode the key sub-node within the pair
     * @param YamlCstNodeRef|null $valueNode the value sub-node, or null for key-only entries (e.g. `key:`)
     */
    public function __construct(
        public array $segments,
        public string $keyText,
        public YamlCstNodeRef $pairNode,
        public YamlCstNodeRef $keyNode,
        public ?YamlCstNodeRef $valueNode,
    ) {
    }

    /**
     * Convenience method — returns the dot-joined path string (e.g. "database.host").
     *
     * Prefer {@see $segments} for programmatic access to avoid ambiguity when key names contain dots.
     */
    public function path(): string
    {
        return implode('.', $this->segments);
    }

    public function keySpan(): YamlSpan
    {
        return $this->keyNode->span();
    }

    /**
     * Returns the raw key text as it appears in $source.
     *
     * This is a convenience wrapper around {@see YamlCstNodeRef::text()} on the key node.
     */
    public function keyText(string $source): string
    {
        return substr($source, $this->keySpan()->startByte, $this->keySpan()->length());
    }

    public function valueSpan(): ?YamlSpan
    {
        return $this->valueNode?->span();
    }

    /**
     * Returns the byte span of the entire mapping pair (key + colon + value).
     *
     * This is a convenience wrapper around {@see YamlCstNodeRef::span()} on the pair node.
     */
    public function pairSpan(): YamlSpan
    {
        return $this->pairNode->span();
    }

    /**
     * Returns the raw value text as it appears in $source, or null for key-only entries (e.g. `key:`).
     *
     * This is a convenience wrapper around {@see YamlCstNodeRef::text()} on the value node.
     */
    public function valueText(string $source): ?string
    {
        return $this->valueNode?->text($source);
    }

    /**
     * Returns the raw text of the entire mapping pair (key + colon + value) as it appears in $source.
     *
     * This is a convenience wrapper around {@see YamlCstNodeRef::text()} on the pair node.
     */
    public function pairText(string $source): string
    {
        return $this->pairNode->text($source);
    }
}
