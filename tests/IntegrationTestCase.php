<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests;

use Mougrim\YamlCst\Factory\YamlLineMapFactory;
use Mougrim\YamlCst\Factory\YamlSyntaxExceptionFactory;
use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;
use Mougrim\YamlCst\YamlCstParser;
use Mougrim\YamlCst\YamlDocumentPatchApplier;
use Mougrim\YamlCst\YamlIndexBuilder;
use Mougrim\YamlCst\YamlPatchConflictChecker;
use Mougrim\YamlCst\YamlTreeSitterCore;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * Shared tree-sitter core — created once per process to avoid repeated FFI initialisation.
     */
    private static ?YamlTreeSitterCore $sharedCore = null;

    protected static function getCore(): YamlTreeSitterCore
    {
        return self::$sharedCore ??= new YamlTreeSitterCoreFactory()->create();
    }

    protected function makeParser(): YamlCstParser
    {
        $lineMapFactory = new YamlLineMapFactory();

        return new YamlCstParser(
            lineMapFactory: $lineMapFactory,
            indexBuilder: new YamlIndexBuilder(
                textStyleHelper: new YamlTextStyleHelper(),
            ),
            syntaxExceptionFactory: new YamlSyntaxExceptionFactory(
                lineMapFactory: $lineMapFactory,
            ),
        );
    }

    protected function makeApplier(): YamlDocumentPatchApplier
    {
        return new YamlDocumentPatchApplier(
            $this->makeParser(),
            conflictChecker: new YamlPatchConflictChecker(),
        );
    }
}
