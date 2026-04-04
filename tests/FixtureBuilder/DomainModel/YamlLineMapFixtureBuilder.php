<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlLineMap;

final class YamlLineMapFixtureBuilder
{
    /** @param list<int> $lineStarts */
    public function build(
        array $lineStarts = [],
    ): YamlLineMap {
        return new YamlLineMap(
            lineStarts: $lineStarts,
        );
    }
}
