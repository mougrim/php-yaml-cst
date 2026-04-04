<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use Mougrim\YamlCst\Dto\YamlTreeSitterTreeHandle;
use Mougrim\YamlCst\YamlTreeSitterCore;

readonly class YamlCstTree
{
    public function __construct(
        private YamlTreeSitterCore $core,
        private YamlTreeSitterTreeHandle $treeHandle,
    ) {
    }

    public function __destruct()
    {
        // ts_tree_delete is required to prevent leaks
        $this->core->deleteTree($this->treeHandle);
    }

    /** @codeCoverageIgnore */
    private function __clone()
    {
    }

    public function root(): YamlCstNodeRef
    {
        $nodeHandle = $this->core->treeRootNode($this->treeHandle);

        return new YamlCstNodeRef($this->core, $this, $nodeHandle);
    }
}
