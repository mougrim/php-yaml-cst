<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\DomainModel\YamlMappingPairRef;
use Mougrim\YamlCst\DomainModel\YamlSpan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlMappingPairRef::class)]
final class YamlMappingPairRefTest extends TestCase
{
    private Stub&YamlCstNodeRef $nodeStub;

    protected function setUp(): void
    {
        $this->nodeStub = $this->createStub(YamlCstNodeRef::class);
    }

    public function testPathReturnsDotJoinedSegments(): void
    {
        $pair = new YamlMappingPairRef(
            segments: ['database', 'host'],
            keyText: 'host',
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: null,
        );

        self::assertSame('database.host', $pair->path());
    }

    public function testValueTextReturnsNullForKeyOnlyPair(): void
    {
        $pair = new YamlMappingPairRef(
            segments: ['key'],
            keyText: 'key',
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: null,
        );

        self::assertNull($pair->valueText('key:'));
    }

    public function testValueSpanReturnsNullForKeyOnlyPair(): void
    {
        $pair = new YamlMappingPairRef(
            segments: ['key'],
            keyText: 'key',
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: null,
        );

        self::assertNull($pair->valueSpan());
    }

    public function testValueTextDelegatesToValueNode(): void
    {
        $valueNode = $this->createStub(YamlCstNodeRef::class);
        $valueNode->method('text')->willReturn('localhost');

        $pair = new YamlMappingPairRef(
            segments: ['host'],
            keyText: 'host',
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: $valueNode,
        );

        self::assertSame('localhost', $pair->valueText('host: localhost'));
    }

    public function testPairTextDelegatesToPairNode(): void
    {
        $pairNode = $this->createStub(YamlCstNodeRef::class);
        $pairNode->method('text')->willReturn('host: localhost');

        $pair = new YamlMappingPairRef(
            segments: ['host'],
            keyText: 'host',
            pairNode: $pairNode,
            keyNode: $this->nodeStub,
            valueNode: null,
        );

        self::assertSame('host: localhost', $pair->pairText('host: localhost'));
    }

    public function testKeyTextExtractsSubstringByKeySpan(): void
    {
        $keyNode = $this->createStub(YamlCstNodeRef::class);
        $keyNode->method('span')->willReturn(new YamlSpan(0, 4));

        $pair = new YamlMappingPairRef(
            segments: ['host'],
            keyText: 'host',
            pairNode: $this->nodeStub,
            keyNode: $keyNode,
            valueNode: null,
        );

        self::assertSame('host', $pair->keyText('host: localhost'));
    }

    public function testPairSpanDelegatesToPairNode(): void
    {
        $pairNode = $this->createStub(YamlCstNodeRef::class);
        $pairNode->method('span')->willReturn(new YamlSpan(0, 14));

        $pair = new YamlMappingPairRef(
            segments: ['host'],
            keyText: 'host',
            pairNode: $pairNode,
            keyNode: $this->nodeStub,
            valueNode: null,
        );

        self::assertSame(
            ['startByte' => 0, 'endByte' => 14],
            ['startByte' => $pair->pairSpan()->startByte, 'endByte' => $pair->pairSpan()->endByte],
        );
    }

    public function testKeySpanDelegatesToKeyNode(): void
    {
        $keyNode = $this->createStub(YamlCstNodeRef::class);
        $keyNode->method('span')->willReturn(new YamlSpan(0, 4));

        $pair = new YamlMappingPairRef(
            segments: ['host'],
            keyText: 'host',
            pairNode: $this->nodeStub,
            keyNode: $keyNode,
            valueNode: null,
        );

        self::assertSame(
            ['startByte' => 0, 'endByte' => 4],
            ['startByte' => $pair->keySpan()->startByte, 'endByte' => $pair->keySpan()->endByte],
        );
    }

    public function testValueSpanDelegatesToValueNode(): void
    {
        $valueNode = $this->createStub(YamlCstNodeRef::class);
        $valueNode->method('span')->willReturn(new YamlSpan(6, 15));

        $pair = new YamlMappingPairRef(
            segments: ['host'],
            keyText: 'host',
            pairNode: $this->nodeStub,
            keyNode: $this->nodeStub,
            valueNode: $valueNode,
        );

        self::assertSame(
            ['startByte' => 6, 'endByte' => 15],
            ['startByte' => $pair->valueSpan()?->startByte, 'endByte' => $pair->valueSpan()?->endByte],
        );
    }
}
