<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\Exception;

use Mougrim\YamlCst\Exception\PathNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathNotFoundException::class)]
final class PathNotFoundExceptionTest extends TestCase
{
    public function testMessage(): void
    {
        self::assertSame(
            'Path not found: foo.bar.baz. Use YamlIndex::find() for a non-throwing lookup.',
            new PathNotFoundException('foo.bar.baz')->getMessage(),
        );
    }
}
