<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use InvalidArgumentException;
use Mougrim\YamlCst\DomainModel\YamlSpan;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlSpanFixtureBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlSpan::class)]
final class YamlSpanTest extends TestCase
{
    /**
     * @return array<string, array{
     *     params: array{startByte: int, endByte: int},
     *     expected: array{startByte: int, endByte: int, length: int},
     * }>
     */
    public static function spanProvider(): array
    {
        return [
            'non-zero start' => [
                'params' => [
                    'startByte' => 10,
                    'endByte' => 20,
                ],
                'expected' => [
                    'startByte' => 10,
                    'endByte' => 20,
                    'length' => 10,
                ],
            ],
            'zero start' => [
                'params' => [
                    'startByte' => 0,
                    'endByte' => 100,
                ],
                'expected' => [
                    'startByte' => 0,
                    'endByte' => 100,
                    'length' => 100,
                ],
            ],
            'zero length' => [
                'params' => [
                    'startByte' => 3,
                    'endByte' => 3,
                ],
                'expected' => [
                    'startByte' => 3,
                    'endByte' => 3,
                    'length' => 0,
                ],
            ],
        ];
    }

    /**
     * @param array{startByte: int, endByte: int} $params
     * @param array{startByte: int, endByte: int, length: int} $expected
     */
    #[DataProvider('spanProvider')]
    public function testProperties(array $params, array $expected): void
    {
        $span = new YamlSpanFixtureBuilder()->build(...$params);

        self::assertSame(
            $expected,
            [
                'startByte' => $span->startByte,
                'endByte' => $span->endByte,
                'length' => $span->length(),
            ],
        );
    }

    /**
     * @return array<string, array{params: array{startByte: int, endByte: int}}>
     */
    public static function invalidSpanProvider(): array
    {
        return [
            'negative start' => [
                'params' => [
                    'startByte' => -1,
                    'endByte' => 10,
                ],
            ],
            'end before start' => [
                'params' => [
                    'startByte' => 10,
                    'endByte' => 5,
                ],
            ],
            'negative start and end' => [
                'params' => [
                    'startByte' => -5,
                    'endByte' => -1,
                ],
            ],
        ];
    }

    /**
     * @param array{startByte: int, endByte: int} $params
     */
    #[DataProvider('invalidSpanProvider')]
    public function testInvalidSpanThrows(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);

        new YamlSpanFixtureBuilder()->build(...$params);
    }
}
