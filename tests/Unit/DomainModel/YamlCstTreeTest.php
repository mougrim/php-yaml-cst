<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\Dto\YamlTreeSitterTreeHandle;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlCstTree::class)]
final class YamlCstTreeTest extends TestCase
{
    public function testDestructorCallsDeleteTree(): void
    {
        $treeHandle = $this->createStub(YamlTreeSitterTreeHandle::class);
        $core = $this->createMock(YamlTreeSitterCore::class);
        $core->expects($this->once())
            ->method('deleteTree')
            ->with($treeHandle)
        ;

        $tree = new YamlCstTree($core, $treeHandle);
        unset($tree);
    }
}
