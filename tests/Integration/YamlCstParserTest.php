<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration;

use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\DomainModel\YamlDocument;
use Mougrim\YamlCst\DomainModel\YamlLocation;
use Mougrim\YamlCst\Dto\YamlTreeSitterNodeHandle;
use Mougrim\YamlCst\Dto\YamlTreeSitterTreeHandle;
use Mougrim\YamlCst\Exception\YamlSyntaxException;
use Mougrim\YamlCst\Factory\YamlSyntaxExceptionFactory;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use Mougrim\YamlCst\YamlCstParser;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function substr;

#[CoversClass(YamlCstParser::class)]
#[CoversClass(YamlCstTree::class)]
#[CoversClass(YamlDocument::class)]
#[CoversClass(YamlSyntaxException::class)]
#[CoversClass(YamlSyntaxExceptionFactory::class)]
#[CoversClass(YamlTreeSitterCore::class)]
#[CoversClass(YamlTreeSitterNodeHandle::class)]
#[CoversClass(YamlTreeSitterTreeHandle::class)]
#[CoversClass(YamlLocation::class)]
final class YamlCstParserTest extends IntegrationTestCase
{
    public function testParseSimpleKeyValue(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame($yaml, $doc->source);
    }

    public function testParseReturnsDocumentWithIndex(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            ['host' => 'host', 'port' => 'port'],
            [
                'host' => $doc->index->get(['host'])->keyText,
                'port' => $doc->index->get(['port'])->keyText,
            ],
        );
    }

    public function testParseNestedYaml(): void
    {
        $yaml = <<<'YAML'
            database:
              host: localhost
              port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            ['host' => 'host', 'port' => 'port'],
            [
                'host' => $doc->index->get(['database', 'host'])->keyText,
                'port' => $doc->index->get(['database', 'port'])->keyText,
            ],
        );
    }

    public function testParseDeeplyNestedYaml(): void
    {
        $yaml = <<<'YAML'
            a:
              b:
                c: value
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame('c', $doc->index->get(['a', 'b', 'c'])->keyText);
    }

    public function testParseFlowStyleYaml(): void
    {
        $yaml = <<<'YAML'
            {name: Alice, age: 30}
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame('name', $doc->index->get(['name'])->keyText);
    }

    /**
     * @return array<string, array{yaml: string, segments: list<string>, expectedKeyText: string}>
     */
    public static function quotedKeyProvider(): array
    {
        return [
            'double-quoted key with dot' => [
                'yaml' => "\"my.key\": value\n",
                'segments' => ['my.key'],  // single segment — key literally named "my.key"
                'expectedKeyText' => 'my.key',
            ],
            'single-quoted key with dot' => [
                'yaml' => "'single.key': value\n",
                'segments' => ['single.key'],  // single segment — key literally named "single.key"
                'expectedKeyText' => 'single.key',
            ],
        ];
    }

    /**
     * @param list<string> $segments
     */
    #[DataProvider('quotedKeyProvider')]
    public function testParseQuotedKeys(string $yaml, array $segments, string $expectedKeyText): void
    {
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame($expectedKeyText, $doc->index->get($segments)->keyText);
    }

    public function testParseInvalidYamlThrowsSyntaxException(): void
    {
        // A tab character at the start of a mapping value is invalid YAML
        $this->expectException(YamlSyntaxException::class);

        $this->makeParser()->parse("key:\n\t- item\n", self::getCore());
    }

    public function testSyntaxExceptionContainsLineNumber(): void
    {
        $this->expectException(YamlSyntaxException::class);
        $this->expectExceptionMessageMatches('/line/i');

        $this->makeParser()->parse("key:\n\t- item\n", self::getCore());
    }

    public function testValueSpanPointsToCorrectText(): void
    {
        $yaml = <<<'YAML'
            key: hello
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $span = $doc->index->get(['key'])->valueSpan();
        $extractedText = $span !== null ? substr($yaml, $span->startByte, $span->length()) : null;

        self::assertSame('hello', $extractedText);
    }

    public function testKeySpanPointsToCorrectText(): void
    {
        $yaml = <<<'YAML'
            mykey: value
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $span = $doc->index->get(['mykey'])->keySpan();
        $extractedText = substr($yaml, $span->startByte, $span->length());

        self::assertSame('mykey', $extractedText);
    }

    public function testIsEmptyReturnsTrueForEmptyString(): void
    {
        $doc = $this->makeParser()->parse('', self::getCore());

        self::assertTrue($doc->isEmpty());
    }

    public function testIsEmptyReturnsTrueForCommentOnlyDocument(): void
    {
        $doc = $this->makeParser()->parse("# only a comment\n", self::getCore());

        self::assertTrue($doc->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyDocument(): void
    {
        $yaml = <<<'YAML'
            key: val
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertFalse($doc->isEmpty());
    }

    public function testIsEmptyReturnsFalseForSequenceOnlyDocument(): void
    {
        // A document with only a sequence has no mapping pairs but is NOT empty.
        $yaml = <<<'YAML'
            - item1
            - item2
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertFalse($doc->isEmpty());
    }

    public function testParseCrlfDocument(): void
    {
        $yaml = "host: localhost\r\nport: 5432\r\n";
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            ['host' => 'host', 'port' => 'port'],
            [
                'host' => $doc->index->get(['host'])->keyText,
                'port' => $doc->index->get(['port'])->keyText,
            ],
        );
    }

    public function testMultiDocumentYamlProducesSyntaxErrorOrFirstDocument(): void
    {
        // Multi-document YAML (with ---) is not supported. The parser either throws
        // YamlSyntaxException or returns only the first document. Either outcome is
        // acceptable — this test documents the actual behaviour so regressions are caught.
        $yaml = "key: value\n---\nother: doc\n";

        try {
            $doc = $this->makeParser()->parse($yaml, self::getCore());
            // If no exception: the first document key must be accessible
            self::assertSame('key', $doc->index->get(['key'])->keyText);
        } catch (YamlSyntaxException $e) {
            // Also acceptable — unsupported input
            self::assertStringContainsString('syntax error', $e->getMessage());
        }
    }

    public function testSyntaxExceptionFromNestedError(): void
    {
        // The first key is valid; the error is in a nested value (unterminated flow sequence).
        // This forces YamlSyntaxExceptionFactory::findFirstError() to recurse through the
        // stream → document → block_mapping → pair chain before finding the ERROR node,
        // exercising the children-search branch of findFirstError().
        $yaml = "a: 1\nb:\n  c: [1, 2\n";

        $this->expectException(YamlSyntaxException::class);

        $this->makeParser()->parse($yaml, self::getCore());
    }
}
