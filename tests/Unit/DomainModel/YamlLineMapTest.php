<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use InvalidArgumentException;
use Mougrim\YamlCst\DomainModel\YamlLineMap;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlLineMapFixtureBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlLineMap::class)]
final class YamlLineMapTest extends TestCase
{
    /**
     * @return array<string, array{
     *     params: array{lineStarts: list<int>},
     *     byteOffset: int,
     *     expected: array{line: int, col: int},
     * }>
     */
    public static function locateProvider(): array
    {
        return [
            'first byte of line 1 (multi-line map)' => [
                'params' => [
                    'lineStarts' => [0, 5, 10],
                ],
                'byteOffset' => 0,
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'middle of line 1 (multi-line map)' => [
                'params' => [
                    'lineStarts' => [0, 5, 10],
                ],
                'byteOffset' => 3,
                'expected' => [
                    'line' => 1,
                    'col' => 4,
                ],
            ],
            'first byte of line 2 (multi-line map)' => [
                'params' => [
                    'lineStarts' => [0, 5, 10],
                ],
                'byteOffset' => 5,
                'expected' => [
                    'line' => 2,
                    'col' => 1,
                ],
            ],
            'middle of line 2 (multi-line map)' => [
                'params' => [
                    'lineStarts' => [0, 5, 10],
                ],
                'byteOffset' => 7,
                'expected' => [
                    'line' => 2,
                    'col' => 3,
                ],
            ],
            'first byte of line 3 (multi-line map)' => [
                'params' => [
                    'lineStarts' => [0, 5, 10],
                ],
                'byteOffset' => 10,
                'expected' => [
                    'line' => 3,
                    'col' => 1,
                ],
            ],
            'byte 0 on single-line' => [
                'params' => [
                    'lineStarts' => [0],
                ],
                'byteOffset' => 0,
                'expected' => [
                    'line' => 1,
                    'col' => 1,
                ],
            ],
            'byte 4 on single-line' => [
                'params' => [
                    'lineStarts' => [0],
                ],
                'byteOffset' => 4,
                'expected' => [
                    'line' => 1,
                    'col' => 5,
                ],
            ],
            'start of line 2 (two-line)' => [
                'params' => [
                    'lineStarts' => [0, 8],
                ],
                'byteOffset' => 8,
                'expected' => [
                    'line' => 2,
                    'col' => 1,
                ],
            ],
            'col 3 of line 2 (two-line)' => [
                'params' => [
                    'lineStarts' => [0, 8],
                ],
                'byteOffset' => 10,
                'expected' => [
                    'line' => 2,
                    'col' => 3,
                ],
            ],
        ];
    }

    /**
     * @param array{lineStarts: list<int>} $params
     * @param array{line: int, col: int} $expected
     */
    #[DataProvider('locateProvider')]
    public function testLocate(array $params, int $byteOffset, array $expected): void
    {
        $lineMap = new YamlLineMapFixtureBuilder()->build(...$params);
        $location = $lineMap->locate($byteOffset);

        self::assertSame(
            $expected,
            [
                'line' => $location->line,
                'col' => $location->col,
            ],
        );
    }

    public function testLocateThrowsForNegativeOffset(): void
    {
        $lineMap = new YamlLineMapFixtureBuilder()->build(lineStarts: [0]);

        $this->expectException(InvalidArgumentException::class);

        $lineMap->locate(-1);
    }
}
