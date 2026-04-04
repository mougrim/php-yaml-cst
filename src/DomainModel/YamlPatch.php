<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

readonly class YamlPatch
{
    public function __construct(
        public YamlSpan $span,
        public string $replacement,
    ) {
    }
}
