<?php

declare(strict_types=1);

namespace Mougrim\YamlCst;

use Mougrim\YamlCst\DomainModel\YamlCstTree;
use Mougrim\YamlCst\DomainModel\YamlDocument;
use Mougrim\YamlCst\Exception\MaxNestingDepthExceededException;
use Mougrim\YamlCst\Exception\YamlSyntaxException;
use Mougrim\YamlCst\Factory\YamlLineMapFactory;
use Mougrim\YamlCst\Factory\YamlSyntaxExceptionFactory;

/**
 * Entry point — parses a YAML string into a {@see YamlDocument}.
 */
readonly class YamlCstParser
{
    public function __construct(
        private YamlLineMapFactory $lineMapFactory,
        private YamlIndexBuilder $indexBuilder,
        private YamlSyntaxExceptionFactory $syntaxExceptionFactory,
    ) {
    }

    /**
     * @throws YamlSyntaxException              if the YAML source has syntax errors
     * @throws MaxNestingDepthExceededException if the document nesting depth exceeds the limit
     */
    public function parse(string $yaml, YamlTreeSitterCore $core): YamlDocument
    {
        $treeHandle = $core->parseString($yaml);
        $tree = new YamlCstTree($core, $treeHandle);
        $root = $tree->root();

        if ($root->hasError()) {
            throw $this->syntaxExceptionFactory->createFromTree($yaml, $root);
        }

        $lineMap = $this->lineMapFactory->create($yaml);
        $index = $this->indexBuilder->build($yaml, $tree);

        return new YamlDocument($yaml, $tree, $index, $lineMap);
    }
}
