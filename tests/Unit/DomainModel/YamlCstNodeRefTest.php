<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\Dto\YamlTreeSitterNodeHandle;
use Mougrim\YamlCst\Dto\YamlTreeSitterTreeHandle;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that YamlCstNodeRef caches each FFI call result and never issues the same FFI
 * call twice. Each test calls a getter twice and asserts both that the value is correct and
 * that the underlying core method was invoked exactly once.
 */
#[CoversClass(YamlCstNodeRef::class)]
final class YamlCstNodeRefTest extends TestCase
{
    private YamlTreeSitterNodeHandle $nodeHandle;
    private YamlCstTree $treeOwner;

    protected function setUp(): void
    {
        $this->nodeHandle = $this->createStub(YamlTreeSitterNodeHandle::class);
        // YamlCstTree has a destructor that accesses its readonly $core property, so we must
        // construct a real instance (with stubbed dependencies) rather than using createStub().
        $treeCore = $this->createStub(YamlTreeSitterCore::class);
        $treeHandle = $this->createStub(YamlTreeSitterTreeHandle::class);
        $this->treeOwner = new YamlCstTree(
            core: $treeCore,
            treeHandle: $treeHandle,
        );
    }

    public function testIsNullIsCachedAfterFirstCall(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeIsNull')
            ->with($this->nodeHandle)
            ->willReturn(false)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => false, 'second' => false],
            ['first' => $node->isNull(), 'second' => $node->isNull()],
        );
    }

    public function testIsNullIsCachedWhenTrue(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeIsNull')
            ->willReturn(true)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => true, 'second' => true],
            ['first' => $node->isNull(), 'second' => $node->isNull()],
        );
    }

    public function testTypeIsCachedAfterFirstCall(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeType')
            ->with($this->nodeHandle)
            ->willReturn(YamlNodeType::BLOCK_MAPPING)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => YamlNodeType::BLOCK_MAPPING, 'second' => YamlNodeType::BLOCK_MAPPING],
            ['first' => $node->type(), 'second' => $node->type()],
        );
    }

    public function testTypeIsCachedWhenNull(): void
    {
        // nodeType() may return null when the type string is not a known YamlNodeType case.
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeType')
            ->willReturn(null)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => null, 'second' => null],
            ['first' => $node->type(), 'second' => $node->type()],
        );
    }

    public function testStartByteIsCachedAfterFirstCall(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeStartByte')
            ->with($this->nodeHandle)
            ->willReturn(42)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => 42, 'second' => 42],
            ['first' => $node->startByte(), 'second' => $node->startByte()],
        );
    }

    public function testEndByteIsCachedAfterFirstCall(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeEndByte')
            ->with($this->nodeHandle)
            ->willReturn(100)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => 100, 'second' => 100],
            ['first' => $node->endByte(), 'second' => $node->endByte()],
        );
    }

    public function testHasErrorIsCachedAfterFirstCall(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeHasError')
            ->with($this->nodeHandle)
            ->willReturn(false)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => false, 'second' => false],
            ['first' => $node->hasError(), 'second' => $node->hasError()],
        );
    }

    public function testIsExtraIsCachedAfterFirstCall(): void
    {
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('nodeIsExtra')
            ->with($this->nodeHandle)
            ->willReturn(true)
        ;

        $node = new YamlCstNodeRef($core, $this->treeOwner, $this->nodeHandle);

        self::assertSame(
            ['first' => true, 'second' => true],
            ['first' => $node->isExtra(), 'second' => $node->isExtra()],
        );
    }
}
