<?php

declare(strict_types=1);

namespace Mougrim\YamlCst;

use LogicException;
use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\DomainModel\YamlIndex;
use Mougrim\YamlCst\DomainModel\YamlIndexNode;
use Mougrim\YamlCst\DomainModel\YamlMappingPairRef;
use Mougrim\YamlCst\Enum\YamlNodeField;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\Exception\MaxNestingDepthExceededException;
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;

/**
 * @internal Walks the CST to build a {@see YamlIndex}. Not part of the public API.
 */
readonly class YamlIndexBuilder
{
    /**
     * Maximum allowed YAML nesting depth. Prevents PHP stack overflow on pathologically deep
     * documents. 512 levels is far beyond any real-world YAML document.
     */
    private const int MAX_NESTING_DEPTH = 512;

    public function __construct(
        private YamlTextStyleHelper $textStyleHelper,
    ) {
    }

    /** @throws MaxNestingDepthExceededException */
    public function build(string $source, YamlCstTree $tree): YamlIndex
    {
        $nodes = [];
        $root = $tree->root();

        $this->walk($source, $root, [], $nodes, 0);

        return new YamlIndex($nodes);
    }

    /**
     * Builds a {@see YamlIndex} directly from an already-computed list of pairs.
     *
     * Useful in tests and other scenarios where a CST is not available.
     *
     * @param list<YamlMappingPairRef> $pairs
     */
    public function buildFromPairs(array $pairs): YamlIndex
    {
        $nodes = [];

        foreach ($pairs as $pair) {
            $this->insertPair($nodes, $pair);
        }

        return new YamlIndex($nodes);
    }

    /**
     * @param list<string> $pathSegments
     * @param array<string, YamlIndexNode> $nodes
     *
     * @throws MaxNestingDepthExceededException
     */
    private function walk(string $source, YamlCstNodeRef $node, array $pathSegments, array &$nodes, int $depth): void
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new MaxNestingDepthExceededException(self::MAX_NESTING_DEPTH);
        }

        if ($node->isNull()) {
            return;
        }

        // Check mapping pairs: block_mapping_pair and flow_pair are the most important
        if ($node->is(YamlNodeType::BLOCK_MAPPING_PAIR) || $node->is(YamlNodeType::FLOW_PAIR)) {
            $keyNode = $node->childByField(YamlNodeField::KEY);

            if ($keyNode->isNull()) {
                return;
            }

            $valueNode = $node->childByField(YamlNodeField::VALUE);
            $keyText = $this->textStyleHelper->normalizeScalar($keyNode->text($source));

            $newPathSegments = [...$pathSegments, $keyText];

            $pair = new YamlMappingPairRef(
                $newPathSegments,
                $keyText,
                $node,
                $keyNode,
                $valueNode->isNull() ? null : $valueNode
            );

            $this->insertPair($nodes, $pair);

            if (!$valueNode->isNull()) {
                $this->walk($source, $valueNode, $newPathSegments, $nodes, $depth + 1);
            }

            return;
        }

        // Recursively traverse named children (like an "almost AST") — this is exactly what tree-sitter suggests
        foreach ($node->namedChildren() as $child) {
            // extra nodes (comments) can be handled separately, but they do not affect the path
            $this->walk($source, $child, $pathSegments, $nodes, $depth + 1);
        }
    }

    /**
     * Inserts a pair into the trie, overwriting any existing pair at the same path.
     *
     * @param array<string, YamlIndexNode> $nodes
     */
    private function insertPair(array &$nodes, YamlMappingPairRef $pair): void
    {
        if ($pair->segments === []) {
            throw new LogicException('YamlMappingPairRef must have at least one segment');
        }

        $level = &$nodes;

        /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
        $targetNode = new YamlIndexNode(); // initialised before loop to satisfy static analysis; overwritten every iteration

        foreach ($pair->segments as $segment) {
            if (!isset($level[$segment])) {
                $level[$segment] = new YamlIndexNode();
            }

            $targetNode = $level[$segment]; // object handle — same object, not a copy
            $level = &$level[$segment]->children;
        }

        $targetNode->pair = $pair;
    }
}
