<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration;

use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use Mougrim\YamlCst\YamlCstSearcher;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\Attributes\CoversClass;

use function array_filter;
use function array_map;
use function count;

#[CoversClass(YamlCstNodeRef::class)]
#[CoversClass(YamlCstSearcher::class)]
#[CoversClass(YamlTreeSitterCore::class)]
final class YamlCstSearcherTest extends IntegrationTestCase
{
    private YamlCstSearcher $searcher;

    protected function setUp(): void
    {
        $this->searcher = new YamlCstSearcher();
    }

    public function testFirstDescendantOfTypeFindsBlockMappingPair(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $result = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        self::assertSame(YamlNodeType::BLOCK_MAPPING_PAIR, $result?->type());
    }

    public function testFirstDescendantOfTypeReturnsNullForMissingType(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $result = $this->searcher->firstDescendantOfType($root, YamlNodeType::FLOW_PAIR);

        self::assertNull($result);
    }

    public function testDirectMappingPairsReturnsAllPairs(): void
    {
        $yaml = <<<'YAML'
            a: 1
            b: 2
            c: 3
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $blockMapping = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING);

        $pairTypes = array_map(
            static fn (YamlCstNodeRef $pair) => $pair->type(),
            $blockMapping ? $this->searcher->directMappingPairs($blockMapping) : [],
        );

        self::assertSame(
            [YamlNodeType::BLOCK_MAPPING_PAIR, YamlNodeType::BLOCK_MAPPING_PAIR, YamlNodeType::BLOCK_MAPPING_PAIR],
            $pairTypes,
        );
    }

    public function testFirstDescendantOfTypeReturnsNullForEmptyFlowMapping(): void
    {
        $yaml = <<<'YAML'
            {}
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $result = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        self::assertNull($result);
    }

    public function testDirectMappingPairsReturnsEmptyForNodeWithNoPairs(): void
    {
        $yaml = <<<'YAML'
            {name: Alice}
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        // The root contains a flow_mapping (not a block_mapping), so directMappingPairs
        // — which only collects BLOCK_MAPPING_PAIR children — must return an empty array.
        $pairs = $this->searcher->directMappingPairs($root);

        self::assertSame([], $pairs);
    }

    public function testIsExtraReturnsTrueForCommentNode(): void
    {
        $yaml = "# comment\nkey: value\n";
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $extraCount = count(array_filter(
            $root->namedChildren(),
            static fn (YamlCstNodeRef $n) => $n->isExtra(),
        ));

        self::assertSame(1, $extraCount);
    }

    public function testIsExtraReturnsFalseForBlockMappingPair(): void
    {
        $yaml = "key: value\n";
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $pair = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        self::assertFalse($pair?->isExtra());
    }

    public function testFirstDescendantOfTypeFindsNodeInDeeplyNestedYaml(): void
    {
        $yaml = <<<'YAML'
            a:
              b:
                c:
                  d: value
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $result = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        self::assertSame(YamlNodeType::BLOCK_MAPPING_PAIR, $result?->type());
    }

    public function testAllDescendantsOfTypeReturnsAllPairs(): void
    {
        $yaml = <<<'YAML'
            a: 1
            b: 2
            c: 3
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $pairs = $this->searcher->allDescendantsOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        self::assertSame(
            [YamlNodeType::BLOCK_MAPPING_PAIR, YamlNodeType::BLOCK_MAPPING_PAIR, YamlNodeType::BLOCK_MAPPING_PAIR],
            array_map(static fn (YamlCstNodeRef $pair) => $pair->type(), $pairs),
        );
    }

    public function testAllDescendantsOfTypeReturnsEmptyForMissingType(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $result = $this->searcher->allDescendantsOfType($root, YamlNodeType::FLOW_PAIR);

        self::assertSame([], $result);
    }

    public function testNextNamedSiblingReturnsNextPair(): void
    {
        $yaml = <<<'YAML'
            a: 1
            b: 2
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $firstPair = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);
        $next = $firstPair?->nextNamedSibling();

        self::assertSame(
            [
                'isNull' => false,
                'type' => YamlNodeType::BLOCK_MAPPING_PAIR,
            ],
            [
                'isNull' => $next?->isNull(),
                'type' => $next?->type(),
            ],
        );
    }

    public function testPreviousNamedSiblingReturnsNullNodeAtFirstPair(): void
    {
        $yaml = <<<'YAML'
            a: 1
            b: 2
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $firstPair = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);
        $prev = $firstPair?->previousNamedSibling();

        // The first pair has no previous sibling — result is a null node
        self::assertTrue($prev?->isNull());
    }

    public function testDirectSequenceItemsReturnsAllItems(): void
    {
        $yaml = <<<'YAML'
            - item1
            - item2
            - item3
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $blockSequence = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_SEQUENCE);
        $items = $blockSequence ? $this->searcher->directSequenceItems($blockSequence) : [];

        self::assertSame(
            [YamlNodeType::BLOCK_SEQUENCE_ITEM, YamlNodeType::BLOCK_SEQUENCE_ITEM, YamlNodeType::BLOCK_SEQUENCE_ITEM],
            array_map(static fn (YamlCstNodeRef $item) => $item->type(), $items),
        );
    }

    public function testDirectSequenceItemsReturnsEmptyForNonSequenceNode(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        // root is not a block_sequence, so directSequenceItems should return empty
        $items = $this->searcher->directSequenceItems($root);

        self::assertSame([], $items);
    }

    public function testNextNamedSiblingRoundTrip(): void
    {
        $yaml = <<<'YAML'
            a: 1
            b: 2
            c: 3
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $pairs = $this->searcher->allDescendantsOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        // Navigate from second to third via nextNamedSibling, and back via previousNamedSibling
        $second = $pairs[1];
        $third = $second->nextNamedSibling();
        $backToSecond = $third->previousNamedSibling();

        self::assertSame(
            [
                'thirdType' => YamlNodeType::BLOCK_MAPPING_PAIR,
                'thirdIsNull' => false,
                'backType' => YamlNodeType::BLOCK_MAPPING_PAIR,
                'backIsNull' => false,
            ],
            [
                'thirdType' => $third->type(),
                'thirdIsNull' => $third->isNull(),
                'backType' => $backToSecond->type(),
                'backIsNull' => $backToSecond->isNull(),
            ],
        );
    }

    public function testFirstDescendantOfTypeReturnsNullForNullNode(): void
    {
        $yaml = <<<'YAML'
            a: 1
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $firstPair = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);
        // previousNamedSibling of the first pair is a null node
        $nullNode = $firstPair?->previousNamedSibling();

        self::assertSame(
            ['isNull' => true, 'result' => null],
            [
                'isNull' => $nullNode?->isNull(),
                'result' => $nullNode !== null ? $this->searcher->firstDescendantOfType($nullNode, YamlNodeType::BLOCK_MAPPING_PAIR) : 'unreachable',
            ],
        );
    }

    public function testAllDescendantsOfTypeReturnsEmptyForNullNode(): void
    {
        $yaml = <<<'YAML'
            a: 1
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $firstPair = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);
        // previousNamedSibling of the first pair is a null node
        $nullNode = $firstPair?->previousNamedSibling();

        self::assertSame(
            ['isNull' => true, 'result' => []],
            [
                'isNull' => $nullNode?->isNull(),
                'result' => $nullNode !== null ? $this->searcher->allDescendantsOfType($nullNode, YamlNodeType::BLOCK_MAPPING_PAIR) : 'unreachable',
            ],
        );
    }

    public function testSpanMatchesStartAndEndBytes(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $root = $this->makeParser()->parse($yaml, self::getCore())->tree->root();
        $pair = $this->searcher->firstDescendantOfType($root, YamlNodeType::BLOCK_MAPPING_PAIR);

        self::assertSame(
            ['startByte' => $pair?->startByte(), 'endByte' => $pair?->endByte()],
            [
                'startByte' => $pair?->span()->startByte,
                'endByte' => $pair?->span()->endByte,
            ],
        );
    }
}
