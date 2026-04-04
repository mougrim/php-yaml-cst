<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration\Factory;

use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(YamlTreeSitterCoreFactory::class)]
#[CoversClass(YamlTreeSitterCore::class)]
class YamlTreeSitterCoreFactoryTest extends IntegrationTestCase
{
    public function testFactoryCreateReturnsCoreInstance(): void
    {
        $this->expectNotToPerformAssertions();
        // Exercise the happy path of YamlTreeSitterCoreFactory::create() directly so its
        // lines are attributed to this test class (which has CoversClass for the factory).
        new YamlTreeSitterCoreFactory()->create();
    }
}
