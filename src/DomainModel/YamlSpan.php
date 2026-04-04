<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use InvalidArgumentException;

readonly class YamlSpan
{
    /**
     * @throws InvalidArgumentException if $startByte < 0 or $endByte < $startByte
     */
    public function __construct(
        public int $startByte,
        public int $endByte,
    ) {
        if ($startByte < 0 || $endByte < $startByte) {
            throw new InvalidArgumentException(
                "Invalid span: startByte={$startByte}, endByte={$endByte}. "
                . 'startByte must be >= 0 and endByte must be >= startByte.',
            );
        }
    }

    public function length(): int
    {
        return $this->endByte - $this->startByte;
    }
}
