<?php

declare(strict_types=1);

namespace Mougrim\YamlCst;

use Mougrim\YamlCst\DomainModel\YamlPatch;
use Mougrim\YamlCst\Exception\PatchConflictException;

use function count;
use function usort;

readonly class YamlPatchConflictChecker
{
    /**
     * Asserts that no two patches in the list overlap.
     *
     * @param list<YamlPatch> $patches
     *
     * @throws PatchConflictException if any two patches overlap
     */
    public function assertNonOverlapping(array $patches): void
    {
        $sorted = $this->sortAscending($patches);

        for ($i = 1, $count = count($sorted); $i < $count; $i++) {
            $previousSpan = $sorted[$i - 1]->span;
            $currentSpan = $sorted[$i]->span;

            if ($currentSpan->startByte < $previousSpan->endByte) {
                throw new PatchConflictException($previousSpan, $currentSpan, $i);
            }
        }
    }

    /**
     * Returns the patches sorted by startByte ascending.
     *
     * @param list<YamlPatch> $patches
     *
     * @return list<YamlPatch>
     */
    private function sortAscending(array $patches): array
    {
        $sorted = $patches;
        usort(
            $sorted,
            static fn (YamlPatch $patch1, YamlPatch $patch2) => $patch1->span->startByte <=> $patch2->span->startByte,
        );

        return $sorted;
    }
}
