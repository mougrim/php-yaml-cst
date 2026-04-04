<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\Factory;

use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\Factory\YamlLineMapFactory;
use Mougrim\YamlCst\Factory\YamlSyntaxExceptionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlSyntaxExceptionFactory::class)]
final class YamlSyntaxExceptionFactoryTest extends TestCase
{
    private YamlSyntaxExceptionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new YamlSyntaxExceptionFactory(new YamlLineMapFactory());
    }

    public function testCreateFromTreeWithDirectErrorNode(): void
    {
        // Root IS an ERROR node → findFirstError returns it immediately with "unexpected content" detail.
        $root = $this->errorNode(startByte: 4);

        $exception = $this->factory->createFromTree("abc\n", $root);

        self::assertSame(
            'YAML syntax error at line 2 (byte 4): unexpected content',
            $exception->getMessage(),
        );
    }

    public function testCreateFromTreeWithNonErrorTypeDetail(): void
    {
        // Covers the "unexpected node type" branch (line 31):
        // findFirstError returns a node whose is(ERROR) stub returns true (so it is "found"),
        // but type() returns a non-ERROR enum value — an inconsistency only possible via a stub.
        $root = $this->createStub(YamlCstNodeRef::class);
        $root->method('isNull')->willReturn(false);
        $root->method('is')->willReturn(true); // any is() call → true, so findFirstError returns it
        $root->method('type')->willReturn(YamlNodeType::BLOCK_MAPPING_PAIR);
        $root->method('startByte')->willReturn(0);

        $exception = $this->factory->createFromTree('a: 1', $root);

        self::assertSame(
            "YAML syntax error at line 1 (byte 0): unexpected node type 'block_mapping_pair'",
            $exception->getMessage(),
        );
    }

    public function testCreateFromTreeRecursiveSearchFindsNestedError(): void
    {
        // Root is NOT an error but a grandchild IS.
        // Covers the children-loop path of findFirstError (lines 51–56) at two recursion levels.
        $errorGrandchild = $this->errorNode(startByte: 5);

        $parent = $this->createStub(YamlCstNodeRef::class);
        $parent->method('isNull')->willReturn(false);
        $parent->method('is')->willReturn(false);
        $parent->method('hasError')->willReturn(true);
        $parent->method('namedChildren')->willReturn([$errorGrandchild]);

        $root = $this->createStub(YamlCstNodeRef::class);
        $root->method('isNull')->willReturn(false);
        $root->method('is')->willReturn(false);
        $root->method('namedChildren')->willReturn([$parent]);

        $exception = $this->factory->createFromTree('hello world', $root);

        self::assertSame(
            'YAML syntax error at line 1 (byte 5): unexpected content',
            $exception->getMessage(),
        );
    }

    public function testCreateFromTreeReturnsGenericMessageWhenNoErrorNodeFound(): void
    {
        // A child has hasError()=true but findFirstError() cannot find an ERROR-type node
        // (MISSING-node scenario). Covers lines 38 and 61 ("no error found" paths).
        $missingChild = $this->createStub(YamlCstNodeRef::class);
        $missingChild->method('isNull')->willReturn(false);
        $missingChild->method('is')->willReturn(false); // NOT an ERROR node
        $missingChild->method('hasError')->willReturn(true);
        $missingChild->method('namedChildren')->willReturn([]);

        $root = $this->createStub(YamlCstNodeRef::class);
        $root->method('isNull')->willReturn(false);
        $root->method('is')->willReturn(false);
        $root->method('namedChildren')->willReturn([$missingChild]);

        $exception = $this->factory->createFromTree('a: 1', $root);

        self::assertSame('YAML syntax error detected in the document', $exception->getMessage());
    }

    public function testFindFirstErrorSkipsNullNode(): void
    {
        // A child has hasError()=true but isNull()=true (covers line 44).
        // findFirstError() recurses into the null child and returns null, so the
        // overall result is also null → generic message (line 38).
        $nullChild = $this->createStub(YamlCstNodeRef::class);
        $nullChild->method('isNull')->willReturn(true);
        $nullChild->method('hasError')->willReturn(true);

        $root = $this->createStub(YamlCstNodeRef::class);
        $root->method('isNull')->willReturn(false);
        $root->method('is')->willReturn(false);
        $root->method('namedChildren')->willReturn([$nullChild]);

        $exception = $this->factory->createFromTree('a: 1', $root);

        self::assertSame('YAML syntax error detected in the document', $exception->getMessage());
    }

    /** Creates a stub that acts as an ERROR-type node at a given byte offset. */
    private function errorNode(int $startByte): Stub&YamlCstNodeRef
    {
        $node = $this->createStub(YamlCstNodeRef::class);
        $node->method('isNull')->willReturn(false);
        $node->method('is')->willReturnCallback(
            static fn (YamlNodeType $type) => $type === YamlNodeType::ERROR,
        );
        $node->method('type')->willReturn(YamlNodeType::ERROR);
        $node->method('hasError')->willReturn(true);
        $node->method('startByte')->willReturn($startByte);
        $node->method('namedChildren')->willReturn([]);

        return $node;
    }
}
