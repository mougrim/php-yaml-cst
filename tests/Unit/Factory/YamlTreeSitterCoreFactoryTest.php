<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\Factory;

use Mougrim\YamlCst\Exception\YamlTreeSitterException;
use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlTreeSitterCoreFactory::class)]
final class YamlTreeSitterCoreFactoryTest extends TestCase
{
    public function testCreateThrowsForMissingCoreLib(): void
    {
        $this->expectException(YamlTreeSitterException::class);
        $this->expectExceptionMessageMatches('~/nonexistent/libtree-sitter-missing\.so~');

        new YamlTreeSitterCoreFactory()->create(
            coreLibPath: '/nonexistent/libtree-sitter-missing.so',
        );
    }

    public function testCreateThrowsForMissingYamlLib(): void
    {
        $this->expectException(YamlTreeSitterException::class);
        $this->expectExceptionMessageMatches('~/nonexistent/libtree-sitter-yaml-missing\.so~');

        new YamlTreeSitterCoreFactory()->create(
            yamlLibPath: '/nonexistent/libtree-sitter-yaml-missing.so',
        );
    }
}
