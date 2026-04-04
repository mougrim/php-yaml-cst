<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\Factory;

use Mougrim\YamlCst\Factory\YamlLineMapFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlLineMapFactory::class)]
final class YamlLineMapFactoryTest extends TestCase
{
    private YamlLineMapFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new YamlLineMapFactory();
    }

    /**
     * @return array<string, array{source: string, offset: int, expected: array{line: int, col: int}}>
     */
    public static function locateProvider(): array
    {
        return [
            'byte 0 of single-line string' => [
                'source' => 'hello',
                'offset' => 0,
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'first byte of second line in two-line string' => [
                'source' => "hello\nworld",
                'offset' => 6,
                'expected' => [
                    'line' => 2,
                    'col' => 1,
                ],
            ],
            'byte 0 of empty source' => [
                'source' => '',
                'offset' => 0,
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'first byte of first line' => [
                'source' => "foo: bar\nbaz: qux\n",
                'offset' => 0,
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'last byte before newline' => [
                'source' => "foo: bar\nbaz: qux\n",
                'offset' => 7,
                'expected' => [
                    'line' => 1,
                    'col' => 8,
                ],
            ],
            'first byte of second line' => [
                'source' => "foo: bar\nbaz: qux\n",
                'offset' => 9,
                'expected' => [
                    'line' => 2,
                    'col' => 1,
                ],
            ],
            'byte on second line' => [
                'source' => "foo: bar\nbaz: qux\n",
                'offset' => 13,
                'expected' => [
                    'line' => 2,
                    'col' => 5,
                ],
            ],
            'first byte of first line (multi-line)' => [
                'source' => "a\nb\nc\n",
                'offset' => 0,
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'first byte of second line (multi-line)' => [
                'source' => "a\nb\nc\n",
                'offset' => 2,
                'expected' => [
                    'line' => 2,
                    'col' => 1,
                ],
            ],
            'first byte of third line (multi-line)' => [
                'source' => "a\nb\nc\n",
                'offset' => 4,
                'expected' => [
                    'line' => 3,
                    'col' => 1,
                ],
            ],
        ];
    }

    /**
     * @param array{line: int, col: int} $expected
     */
    #[DataProvider('locateProvider')]
    public function testLocate(string $source, int $offset, array $expected): void
    {
        $lineMap = $this->factory->create($source);
        $location = $lineMap->locate($offset);

        self::assertSame(
            $expected,
            [
                'line' => $location->line,
                'col' => $location->col,
            ],
        );
    }
}
