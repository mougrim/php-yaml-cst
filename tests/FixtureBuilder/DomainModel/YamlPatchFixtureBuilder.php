<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlPatch;
use Mougrim\YamlCst\DomainModel\YamlSpan;

final class YamlPatchFixtureBuilder
{
    private YamlSpanFixtureBuilder $yamlSpanFixtureBuilder;

    public function __construct()
    {
        $this->yamlSpanFixtureBuilder = new YamlSpanFixtureBuilder();
    }

    public function build(
        ?YamlSpan $span = null,
        string $replacement = '',
    ): YamlPatch {
        return new YamlPatch(
            span: $span ?? $this->yamlSpanFixtureBuilder->build(),
            replacement: $replacement,
        );
    }
}
