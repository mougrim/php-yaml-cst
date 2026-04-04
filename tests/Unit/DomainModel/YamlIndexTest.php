<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use LogicException;
use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\DomainModel\YamlIndex;
use Mougrim\YamlCst\DomainModel\YamlMappingPairRef;
use Mougrim\YamlCst\Dto\YamlTreeSitterNodeHandle;
use Mougrim\YamlCst\Dto\YamlTreeSitterTreeHandle;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\Exception\PathNotFoundException;
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;
use Mougrim\YamlCst\YamlIndexBuilder;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function count;
use function sort;

#[CoversClass(YamlIndex::class)]
#[CoversClass(YamlIndexBuilder::class)]
final class YamlIndexTest extends TestCase
{
    private YamlIndexBuilder $indexBuilder;
    private Stub&YamlTextStyleHelper $textStyleHelperStub;
    private Stub&YamlCstNodeRef $nodeStub;

    protected function setUp(): void
    {
        $this->textStyleHelperStub = $this->createStub(YamlTextStyleHelper::class);
        $this->indexBuilder = new YamlIndexBuilder(
            textStyleHelper: $this->textStyleHelperStub,
        );
        $this->nodeStub = $this->createStub(YamlCstNodeRef::class);
    }

    public function testAddAndGet(): void
    {
        $pair = $this->makePair(segments: ['database', 'host'], keyText: 'host');
        $index = $this->makeIndex($pair);

        self::assertSame($pair, $index->get(['database', 'host']));
    }

    public function testGetThrowsForMissingPath(): void
    {
        $this->expectException(PathNotFoundException::class);
        $this->expectExceptionMessage('Path not found: missing.key. Use YamlIndex::find() for a non-throwing lookup.');

        $this->makeIndex()->get(['missing', 'key']);
    }

    public function testAllPairs(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['a'], keyText: 'a'),
            $this->makePair(segments: ['b'], keyText: 'b'),
        );

        $keys = array_map(static fn (YamlMappingPairRef $pair) => $pair->keyText, $index->allPairs());
        sort($keys);

        self::assertSame(['a', 'b'], $keys);
    }

    public function testChildrenOf(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['db'], keyText: 'db'),
            $this->makePair(segments: ['db', 'host'], keyText: 'host'),
            $this->makePair(segments: ['db', 'port'], keyText: 'port'),
            // 'credentials' itself has no pair (pair === null), so it won't appear in
            // childrenOf(['db']). 'user' is a grandchild and is also excluded.
            $this->makePair(segments: ['db', 'credentials', 'user'], keyText: 'user'),
        );

        $keys = array_map(static fn (YamlMappingPairRef $pair) => $pair->keyText, $index->childrenOf(['db']));
        sort($keys);

        self::assertSame(['host', 'port'], $keys);
    }

    public function testChildrenOfByPathWithTrailingDot(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['app', 'name'], keyText: 'name'),
        );

        $keys = array_map(static fn (YamlMappingPairRef $pair) => $pair->keyText, $index->childrenOfByPath('app.'));

        self::assertSame(['name'], $keys);
    }

    public function testChildrenOfEmptyResult(): void
    {
        self::assertSame([], $this->makeIndex()->childrenOf(['nonexistent']));
    }

    public function testChildrenOfEmptySegmentsReturnsRootChildren(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['alpha'], keyText: 'alpha'),
            $this->makePair(segments: ['beta'], keyText: 'beta'),
            $this->makePair(segments: ['beta', 'nested'], keyText: 'nested'),
        );

        $keys = array_map(static fn (YamlMappingPairRef $pair) => $pair->keyText, $index->childrenOf([]));
        sort($keys);

        self::assertSame(['alpha', 'beta'], $keys);
    }

    public function testFindReturnsNullForMissingPath(): void
    {
        self::assertNull($this->makeIndex()->find(['nonexistent', 'path']));
    }

    public function testFindReturnsPairForExistingPath(): void
    {
        $pair = $this->makePair(segments: ['database', 'host'], keyText: 'host');
        $index = $this->makeIndex($pair);

        self::assertSame($pair, $index->find(['database', 'host']));
    }

    public function testHasReturnsTrueForExistingPath(): void
    {
        $index = $this->makeIndex($this->makePair(segments: ['database', 'host'], keyText: 'host'));

        self::assertTrue($index->has(['database', 'host']));
    }

    public function testHasReturnsFalseForMissingPath(): void
    {
        self::assertFalse($this->makeIndex()->has(['nonexistent', 'path']));
    }

    public function testBuildFromPairsThrowsForEmptySegments(): void
    {
        $emptySegmentsPair = new YamlMappingPairRef(
            segments: [],
            keyText: 'key',
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: null,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('YamlMappingPairRef must have at least one segment');

        $this->indexBuilder->buildFromPairs([$emptySegmentsPair]);
    }

    public function testBuildWithNullRootBuildsEmptyIndex(): void
    {
        $treeHandle = $this->createStub(YamlTreeSitterTreeHandle::class);
        $nodeHandle = $this->createStub(YamlTreeSitterNodeHandle::class);

        $core = $this->createStub(YamlTreeSitterCore::class);
        $core->method('treeRootNode')->willReturn($nodeHandle);
        $core->method('nodeIsNull')->willReturn(true);

        $tree = new YamlCstTree($core, $treeHandle);
        $index = $this->indexBuilder->build('', $tree);

        self::assertSame([], $index->allPairs());
    }

    public function testBuildSkipsMappingPairWithNullKeyNode(): void
    {
        $treeHandle = $this->createStub(YamlTreeSitterTreeHandle::class);
        $rootHandle = $this->createStub(YamlTreeSitterNodeHandle::class);
        $keyHandle = $this->createStub(YamlTreeSitterNodeHandle::class);

        $core = $this->createStub(YamlTreeSitterCore::class);
        $core->method('treeRootNode')->willReturn($rootHandle);
        $core->method('nodeIsNull')->willReturnCallback(
            static fn (YamlTreeSitterNodeHandle $handle) => $handle === $keyHandle,
        );
        $core->method('nodeType')->willReturn(YamlNodeType::BLOCK_MAPPING_PAIR);
        $core->method('nodeChildByFieldName')->willReturn($keyHandle);

        $tree = new YamlCstTree($core, $treeHandle);
        $index = $this->indexBuilder->build('key: value', $tree);

        self::assertSame([], $index->allPairs());
    }

    public function testBuildFromPairsOverwritesSamePath(): void
    {
        $firstPair = $this->makePair(segments: ['key'], keyText: 'key');
        $secondPair = $this->makePair(segments: ['key'], keyText: 'key');
        $index = $this->makeIndex($firstPair, $secondPair);

        self::assertSame(
            ['pair' => $secondPair, 'pairCount' => 1],
            ['pair' => $index->get(['key']), 'pairCount' => count($index->allPairs())],
        );
    }

    public function testAllPaths(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['a'], keyText: 'a'),
            $this->makePair(segments: ['b'], keyText: 'b'),
        );

        self::assertSame([['a'], ['b']], $index->allPaths());
    }

    public function testAllDotPaths(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['a'], keyText: 'a'),
            $this->makePair(segments: ['b'], keyText: 'b'),
        );

        self::assertSame(['a', 'b'], $index->allDotPaths());
    }

    public function testGetByPath(): void
    {
        $pair = $this->makePair(segments: ['database', 'host'], keyText: 'host');
        $index = $this->makeIndex($pair);

        self::assertSame($pair, $index->getByPath('database.host'));
    }

    public function testFindByPathReturnsNullForMissingPath(): void
    {
        self::assertNull($this->makeIndex()->findByPath('nonexistent.path'));
    }

    public function testHasByPath(): void
    {
        $index = $this->makeIndex($this->makePair(segments: ['database', 'host'], keyText: 'host'));

        self::assertSame(
            [
                'exists' => true,
                'missing' => false,
            ],
            [
                'exists' => $index->hasByPath('database.host'),
                'missing' => $index->hasByPath('database.port'),
            ],
        );
    }

    public function testChildrenOfByPath(): void
    {
        $index = $this->makeIndex(
            $this->makePair(segments: ['db'], keyText: 'db'),
            $this->makePair(segments: ['db', 'host'], keyText: 'host'),
            $this->makePair(segments: ['db', 'port'], keyText: 'port'),
        );

        $keys = array_map(
            static fn (YamlMappingPairRef $pair) => $pair->keyText,
            $index->childrenOfByPath('db'),
        );

        self::assertSame(['host', 'port'], $keys);
    }

    public function testPairPathMethod(): void
    {
        $pair = $this->makePair(segments: ['database', 'host'], keyText: 'host');

        self::assertSame('database.host', $pair->path());
    }

    private function makeIndex(YamlMappingPairRef ...$pairs): YamlIndex
    {
        return $this->indexBuilder->buildFromPairs(array_values($pairs));
    }

    /** @param list<string> $segments */
    private function makePair(array $segments, string $keyText): YamlMappingPairRef
    {
        return new YamlMappingPairRef(
            segments: $segments,
            keyText: $keyText,
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: null,
        );
    }
}
