<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlPatch;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlPatchFixtureBuilder;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlSpanFixtureBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlPatch::class)]
final class YamlPatchTest extends TestCase
{
    /**
     * @return array<string, array{startByte: int, endByte: int, replacement: string}>
     */
    public static function patchProvider(): array
    {
        return [
            'with replacement' => [
                'startByte' => 5,
                'endByte' => 10,
                'replacement' => 'replacement',
            ],
            'empty replacement' => [
                'startByte' => 0,
                'endByte' => 5,
                'replacement' => '',
            ],
        ];
    }

    #[DataProvider('patchProvider')]
    public function testProperties(int $startByte, int $endByte, string $replacement): void
    {
        $span = new YamlSpanFixtureBuilder()->build(startByte: $startByte, endByte: $endByte);
        $patch = new YamlPatchFixtureBuilder()->build(span: $span, replacement: $replacement);

        self::assertSame(
            [
                'span' => $span,
                'replacement' => $replacement,
            ],
            [
                'span' => $patch->span,
                'replacement' => $patch->replacement,
            ],
        );
    }
}
