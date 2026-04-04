<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel;

use Mougrim\YamlCst\DomainModel\YamlSpan;

final class YamlSpanFixtureBuilder
{
    public function build(
        int $startByte = 0,
        int $endByte = 0,
    ): YamlSpan {
        return new YamlSpan(
            startByte: $startByte,
            endByte: $endByte,
        );
    }
}
