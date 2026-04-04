<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit;

use Mougrim\YamlCst\Exception\PatchConflictException;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlPatchFixtureBuilder;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlSpanFixtureBuilder;
use Mougrim\YamlCst\YamlPatchConflictChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlPatchConflictChecker::class)]
final class YamlPatchConflictCheckerTest extends TestCase
{
    private YamlPatchConflictChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new YamlPatchConflictChecker();
    }

    public function testAssertNonOverlappingPassesForUnsortedNonOverlappingPatches(): void
    {
        // Patches are intentionally passed in reverse (end→start) order.
        // assertNonOverlapping() must sort them before checking, so no conflict should be detected.
        $this->expectNotToPerformAssertions();

        $this->checker->assertNonOverlapping([
            new YamlPatchFixtureBuilder()->build(
                span: new YamlSpanFixtureBuilder()->build(startByte: 20, endByte: 25),
                replacement: 'C',
            ),
            new YamlPatchFixtureBuilder()->build(
                span: new YamlSpanFixtureBuilder()->build(startByte: 10, endByte: 15),
                replacement: 'B',
            ),
            new YamlPatchFixtureBuilder()->build(
                span: new YamlSpanFixtureBuilder()->build(endByte: 5),
                replacement: 'A',
            ),
        ]);
    }

    public function testAssertNonOverlappingPassesForAdjacentPatches(): void
    {
        $this->expectNotToPerformAssertions();

        $firstSpan = new YamlSpanFixtureBuilder()->build(endByte: 5);
        $secondSpan = new YamlSpanFixtureBuilder()->build(startByte: 5, endByte: 10);

        $this->checker->assertNonOverlapping([
            new YamlPatchFixtureBuilder()->build(span: $firstSpan, replacement: 'A'),
            new YamlPatchFixtureBuilder()->build(span: $secondSpan, replacement: 'B'),
        ]);
    }

    public function testOverlappingPatchesThrow(): void
    {
        $firstSpan = new YamlSpanFixtureBuilder()->build(endByte: 10);
        $overlappingSpan = new YamlSpanFixtureBuilder()->build(startByte: 5, endByte: 15);

        $this->expectException(PatchConflictException::class);
        $this->expectExceptionMessageMatches('/previous span bytes \[0, 10\).*current span bytes \[5, 15\)/');

        $this->checker->assertNonOverlapping([
            new YamlPatchFixtureBuilder()->build(span: $firstSpan, replacement: 'A'),
            new YamlPatchFixtureBuilder()->build(span: $overlappingSpan, replacement: 'B'),
        ]);
    }

    public function testOverlappingPatchesExceptionExposesSpans(): void
    {
        $firstSpan = new YamlSpanFixtureBuilder()->build(endByte: 10);
        $overlappingSpan = new YamlSpanFixtureBuilder()->build(startByte: 5, endByte: 15);

        try {
            $this->checker->assertNonOverlapping([
                new YamlPatchFixtureBuilder()->build(span: $firstSpan, replacement: 'A'),
                new YamlPatchFixtureBuilder()->build(span: $overlappingSpan, replacement: 'B'),
            ]);
            self::fail('Expected PatchConflictException');
        } catch (PatchConflictException $e) {
            self::assertSame(
                [
                    'previousSpan.startByte' => 0,
                    'previousSpan.endByte' => 10,
                    'currentSpan.startByte' => 5,
                    'currentSpan.endByte' => 15,
                ],
                [
                    'previousSpan.startByte' => $e->previousSpan->startByte,
                    'previousSpan.endByte' => $e->previousSpan->endByte,
                    'currentSpan.startByte' => $e->currentSpan->startByte,
                    'currentSpan.endByte' => $e->currentSpan->endByte,
                ],
            );
        }
    }

    public function testEmptyListPassesWithoutException(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->assertNonOverlapping([]);
    }
}
