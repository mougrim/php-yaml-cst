<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use Mougrim\YamlCst\Exception\PathNotFoundException;
use Mougrim\YamlCst\YamlIndexBuilder;

use function array_map;
use function explode;
use function implode;
use function rtrim;

readonly class YamlIndex
{
    /**
     * @param array<string, YamlIndexNode> $nodes root level of the trie, in document order
     *
     * @internal Constructed by {@see YamlIndexBuilder}. Not intended to be instantiated directly from user code.
     */
    public function __construct(
        private array $nodes = [],
    ) {
    }

    /** @return list<YamlMappingPairRef> all indexed pairs in document order */
    public function allPairs(): array
    {
        $pairs = [];
        $this->collectPairs($this->nodes, $pairs);

        return $pairs;
    }

    /** @return list<list<string>> all indexed segment paths in document order */
    public function allPaths(): array
    {
        return array_map(static fn (YamlMappingPairRef $pair) => $pair->segments, $this->allPairs());
    }

    /**
     * Convenience variant of {@see allPaths()} — returns dot-joined path strings.
     *
     * **Warning:** if any key in your document literally contains a dot (e.g. `"my.key": value`),
     * the returned strings will be ambiguous. Use {@see allPaths()} with segment arrays instead.
     *
     * @return list<string>
     */
    public function allDotPaths(): array
    {
        return array_map(static fn (YamlMappingPairRef $pair) => $pair->path(), $this->allPairs());
    }

    /** @param list<string> $segments */
    public function has(array $segments): bool
    {
        $node = $this->findNode($segments);

        return $node !== null && $node->pair !== null;
    }

    /** @param list<string> $segments */
    public function find(array $segments): ?YamlMappingPairRef
    {
        return $this->findNode($segments)?->pair;
    }

    /**
     * @param list<string> $segments
     *
     * @throws PathNotFoundException
     */
    public function get(array $segments): YamlMappingPairRef
    {
        $pair = $this->findNode($segments)?->pair;

        return $pair ?? throw new PathNotFoundException(implode('.', $segments));
    }

    /**
     * Returns direct children of the given parent path.
     *
     * @param list<string> $segments
     *
     * @return list<YamlMappingPairRef> direct children in document order
     */
    public function childrenOf(array $segments): array
    {
        if ($segments) {
            $node = $this->findNode($segments);
            $children = $node->children ?? [];
        } else {
            $children = $this->nodes;
        }

        $pairs = [];
        foreach ($children as $child) {
            if ($child->pair !== null) {
                $pairs[] = $child->pair;
            }
        }

        return $pairs;
    }

    /**
     * Dot-path convenience variant of {@see has()} — splits $path on '.' and delegates.
     *
     * Prefer {@see has()} with an explicit segments array when key names may contain dots.
     */
    public function hasByPath(string $path): bool
    {
        return $this->has(explode('.', $path));
    }

    /**
     * Dot-path convenience variant of {@see find()} — splits $path on '.' and delegates.
     *
     * Prefer {@see find()} with an explicit segments array when key names may contain dots.
     */
    public function findByPath(string $path): ?YamlMappingPairRef
    {
        return $this->find(explode('.', $path));
    }

    /**
     * Dot-path convenience variant of {@see get()} — splits $path on '.' and delegates.
     *
     * Prefer {@see get()} with an explicit segments array when key names may contain dots.
     *
     * @throws PathNotFoundException
     */
    public function getByPath(string $path): YamlMappingPairRef
    {
        return $this->get(explode('.', $path));
    }

    /**
     * Dot-path convenience variant of {@see childrenOf()} — splits $prefix on '.' and delegates.
     *
     * Prefer {@see childrenOf()} with an explicit segments array when key names may contain dots.
     *
     * @return list<YamlMappingPairRef> direct children in document order
     */
    public function childrenOfByPath(string $prefix): array
    {
        return $this->childrenOf(explode('.', rtrim($prefix, '.')));
    }

    /** @param list<string> $segments */
    private function findNode(array $segments): ?YamlIndexNode
    {
        $level = $this->nodes;
        $node = null;

        foreach ($segments as $segment) {
            if (!isset($level[$segment])) {
                return null;
            }

            $node = $level[$segment];
            $level = $node->children;
        }

        return $node;
    }

    /**
     * @param array<string, YamlIndexNode> $nodes
     * @param list<YamlMappingPairRef> $pairs
     */
    private function collectPairs(array $nodes, array &$pairs): void
    {
        foreach ($nodes as $node) {
            if ($node->pair !== null) {
                $pairs[] = $node->pair;
            }

            $this->collectPairs($node->children, $pairs);
        }
    }
}
