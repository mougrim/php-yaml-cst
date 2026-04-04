<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration;

use Mougrim\YamlCst\DomainModel\YamlMappingPairRef;
use Mougrim\YamlCst\Exception\PathNotFoundException;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use Mougrim\YamlCst\YamlIndexBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;
use function sort;
use function substr;

#[CoversClass(YamlIndexBuilder::class)]
final class YamlIndexBuilderTest extends IntegrationTestCase
{
    public function testFlatIndex(): void
    {
        $yaml = <<<'YAML'
            alpha: 1
            beta: 2
            gamma: 3
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            [
                'alpha' => 'alpha',
                'beta' => 'beta',
                'gamma' => 'gamma',
            ],
            [
                'alpha' => $doc->index->get(['alpha'])->keyText,
                'beta' => $doc->index->get(['beta'])->keyText,
                'gamma' => $doc->index->get(['gamma'])->keyText,
            ],
        );
    }

    public function testNestedIndex(): void
    {
        $yaml = <<<'YAML'
            outer:
              inner: value
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame('inner', $doc->index->get(['outer', 'inner'])->keyText);
    }

    public function testAllPairsContainsAllKeys(): void
    {
        $yaml = <<<'YAML'
            foo: 1
            bar: 2
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $paths = array_map(static fn (YamlMappingPairRef $pair) => $pair->keyText, $doc->index->allPairs());
        sort($paths);

        self::assertSame(['bar', 'foo'], $paths);
    }

    public function testChildrenOfReturnsDirectChildren(): void
    {
        $yaml = <<<'YAML'
            db:
              host: localhost
              port: 5432
              credentials:
                user: root
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $keys = array_map(static fn (YamlMappingPairRef $pair) => $pair->keyText, $doc->index->childrenOf(['db']));
        sort($keys);

        self::assertSame(['credentials', 'host', 'port'], $keys);
    }

    public function testChildrenOfReturnsEmptyArrayForMissingPath(): void
    {
        $yaml = <<<'YAML'
            foo: bar
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            ['children' => []],
            ['children' => $doc->index->childrenOf(['nonexistent'])],
        );
    }

    public function testMissingPathThrows(): void
    {
        $yaml = <<<'YAML'
            foo: bar
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        $this->expectException(PathNotFoundException::class);

        $doc->index->get(['foo', 'nonexistent']);
    }

    public function testValueNodeIsNullForKeyOnlyMapping(): void
    {
        $yaml = <<<'YAML'
            section:
              key:
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            [
                'valueSpan' => null,
                'valueText' => null,
            ],
            [
                'valueSpan' => $doc->index->get(['section', 'key'])->valueSpan(),
                'valueText' => $doc->index->get(['section', 'key'])->valueText($yaml),
            ],
        );
    }

    public function testIndexHandlesMixedDepths(): void
    {
        $yaml = <<<'YAML'
            top: topval
            nested:
              child: nestedval
            other: otherval
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $topSpan = $doc->index->get(['top'])->valueSpan();
        $nestedSpan = $doc->index->get(['nested', 'child'])->valueSpan();
        $otherSpan = $doc->index->get(['other'])->valueSpan();

        self::assertSame(
            [
                'top' => 'topval',
                'nested.child' => 'nestedval',
                'other' => 'otherval',
            ],
            [
                'top' => $topSpan !== null ? substr($yaml, $topSpan->startByte, $topSpan->length()) : null,
                'nested.child' => $nestedSpan !== null ? substr($yaml, $nestedSpan->startByte, $nestedSpan->length()) : null,
                'other' => $otherSpan !== null ? substr($yaml, $otherSpan->startByte, $otherSpan->length()) : null,
            ],
        );
    }

    /** @return array<string, array{segments: list<string>, expected: array{line: int, col: int}}> */
    public static function lineMapLocationProvider(): array
    {
        return [
            'first key on line 1' => [
                'segments' => ['first'],
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'second key on line 2' => [
                'segments' => ['second'],
                'expected' => [
                    'line' => 2,
                    'col' => 1,
                ],
            ],
        ];
    }

    /**
     * @param list<string> $segments
     * @param array{line: int, col: int} $expected
     */
    #[DataProvider('lineMapLocationProvider')]
    public function testLineMapLocatesKey(array $segments, array $expected): void
    {
        $yaml = <<<'YAML'
            first: value
            second: other
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $keyStartByte = $doc->index->get($segments)->keySpan()->startByte;
        $location = $doc->lineMap->locate($keyStartByte);

        self::assertSame(
            $expected,
            [
                'line' => $location->line,
                'col' => $location->col,
            ],
        );
    }

    public function testFlowStyleNestedMappings(): void
    {
        $yaml = <<<'YAML'
            config: {host: localhost, port: 5432}
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame(
            ['host' => 'host', 'port' => 'port'],
            [
                'host' => $doc->index->get(['config', 'host'])->keyText,
                'port' => $doc->index->get(['config', 'port'])->keyText,
            ],
        );
    }

    /**
     * @return array<string, array{yaml: string, segments: list<string>, expectedKeyText: string}>
     */
    public static function normalizeKeyEscapeProvider(): array
    {
        return [
            'double-quoted key with escaped double quote' => [
                'yaml' => "\"foo\\\"bar\": value\n",
                'segments' => ['foo"bar'],
                'expectedKeyText' => 'foo"bar',
            ],
            'single-quoted key with escaped single quote' => [
                'yaml' => "'foo''bar': value\n",
                'segments' => ["foo'bar"],
                'expectedKeyText' => "foo'bar",
            ],
        ];
    }

    /**
     * @param list<string> $segments
     */
    #[DataProvider('normalizeKeyEscapeProvider')]
    public function testNormalizeKeyUnescapesQuotes(string $yaml, array $segments, string $expectedKeyText): void
    {
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        self::assertSame($expectedKeyText, $doc->index->get($segments)->keyText);
    }
}
