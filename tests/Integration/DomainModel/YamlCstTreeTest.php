<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration\DomainModel;

use Error;
use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(YamlCstTree::class)]
class YamlCstTreeTest extends IntegrationTestCase
{
    public function testCloneIsForbidden(): void
    {
        $core = self::getCore();
        $parser = $this->makeParser();
        $document = $parser->parse('', $core);
        $tree = $document->tree;
        $this->expectException(Error::class);
        // PHP 8.5 changed the error message format: added "method" after "private"
        $expectedMessage = PHP_VERSION_ID >= 80500
            ? 'Call to private method Mougrim\YamlCst\DomainModel\YamlCstTree::__clone() from scope Mougrim\YamlCst\Tests\Integration\DomainModel\YamlCstTreeTest'
            : 'Call to private Mougrim\YamlCst\DomainModel\YamlCstTree::__clone() from scope Mougrim\YamlCst\Tests\Integration\DomainModel\YamlCstTreeTest';
        $this->expectExceptionMessage($expectedMessage);

        /** @noinspection PhpExpressionResultUnusedInspection */
        /** @phpstan-ignore expr.resultUnused */
        clone $tree;
    }
}
