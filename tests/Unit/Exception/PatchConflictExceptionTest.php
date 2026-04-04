<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\Exception;

use Mougrim\YamlCst\Exception\PatchConflictException;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlSpanFixtureBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatchConflictException::class)]
final class PatchConflictExceptionTest extends TestCase
{
    public function testMessageAndSpans(): void
    {
        $previousSpan = new YamlSpanFixtureBuilder()->build(endByte: 10);
        $currentSpan = new YamlSpanFixtureBuilder()->build(startByte: 5, endByte: 15);
        $exception = new PatchConflictException($previousSpan, $currentSpan, 1);

        self::assertSame(
            [
                'message' => 'Overlapping patches at index 1: previous span bytes [0, 10), current span bytes [5, 15)',
                'previousSpan' => $previousSpan,
                'currentSpan' => $currentSpan,
            ],
            [
                'message' => $exception->getMessage(),
                'previousSpan' => $exception->previousSpan,
                'currentSpan' => $exception->currentSpan,
            ],
        );
    }
}
