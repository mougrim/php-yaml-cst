<?php

declare(strict_types=1);

namespace Mougrim\YamlCst;

use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\DomainModel\YamlIndex;
use Mougrim\YamlCst\Enum\YamlNodeType;

/**
 * Tree-navigation helpers for working with the YAML Concrete Syntax Tree.
 *
 * Useful for locating specific node types or iterating over mapping pairs
 * when you need lower-level access beyond what {@see YamlIndex} provides.
 */
readonly class YamlCstSearcher
{
    /**
     * Returns all descendants (including $node itself) that match $type, in depth-first order.
     *
     * @return list<YamlCstNodeRef>
     */
    public function allDescendantsOfType(YamlCstNodeRef $node, YamlNodeType $type): array
    {
        $results = [];
        $this->collectDescendantsOfType($node, $type, $results);

        return $results;
    }

    public function firstDescendantOfType(YamlCstNodeRef $node, YamlNodeType $type): ?YamlCstNodeRef
    {
        if ($node->isNull()) {
            return null;
        }

        if ($node->is($type)) {
            return $node;
        }

        foreach ($node->namedChildren() as $child) {
            $result = $this->firstDescendantOfType($child, $type);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Returns the direct BLOCK_SEQUENCE_ITEM children of a block-sequence node.
     *
     * Pass a node of type {@see YamlNodeType::BLOCK_SEQUENCE}. Only block-sequence items are
     * returned; other child types (e.g. comments) are skipped.
     *
     * The caller is responsible for ensuring $blockSequence is not a null node
     * (i.e. {@see YamlCstNodeRef::isNull()} returns false). Passing a null node returns an
     * empty array since null nodes have no named children.
     *
     * @return list<YamlCstNodeRef>
     */
    public function directSequenceItems(YamlCstNodeRef $blockSequence): array
    {
        $items = [];

        foreach ($blockSequence->namedChildren() as $child) {
            if ($child->is(YamlNodeType::BLOCK_SEQUENCE_ITEM)) {
                $items[] = $child;
            }
        }

        return $items;
    }

    /**
     * Returns the direct BLOCK_MAPPING_PAIR children of a block-mapping node.
     *
     * Pass a node of type {@see YamlNodeType::BLOCK_MAPPING}. Only block-mapping pairs are
     * returned; flow pairs (inside `{…}`) are not included.
     *
     * The caller is responsible for ensuring $blockMapping is not a null node
     * (i.e. {@see YamlCstNodeRef::isNull()} returns false). Passing a null node returns an
     * empty array since null nodes have no named children.
     *
     * @return list<YamlCstNodeRef>
     */
    public function directMappingPairs(YamlCstNodeRef $blockMapping): array
    {
        $pairs = [];

        foreach ($blockMapping->namedChildren() as $child) {
            if ($child->is(YamlNodeType::BLOCK_MAPPING_PAIR)) {
                $pairs[] = $child;
            }
        }

        return $pairs;
    }

    /**
     * @param list<YamlCstNodeRef> $results
     */
    private function collectDescendantsOfType(YamlCstNodeRef $node, YamlNodeType $type, array &$results): void
    {
        if ($node->isNull()) {
            return;
        }

        if ($node->is($type)) {
            $results[] = $node;
        }

        foreach ($node->namedChildren() as $child) {
            $this->collectDescendantsOfType($child, $type, $results);
        }
    }
}
