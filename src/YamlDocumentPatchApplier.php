<?php

declare(strict_types=1);

namespace Mougrim\YamlCst;

use Mougrim\YamlCst\DomainModel\YamlDocument;
use Mougrim\YamlCst\DomainModel\YamlPatch;
use Mougrim\YamlCst\Exception\PatchConflictException;
use Mougrim\YamlCst\Exception\YamlSyntaxException;

use function substr;
use function usort;

/**
 * Applies a list of patches to a {@see YamlDocument} and returns a new, re-parsed document.
 */
readonly class YamlDocumentPatchApplier
{
    public function __construct(
        private YamlCstParser $parser,
        private YamlPatchConflictChecker $conflictChecker,
    ) {
    }

    /**
     * @param list<YamlPatch> $patches patches to apply; must be non-overlapping
     * @param YamlTreeSitterCore $core the same singleton instance used for all parsing operations in the process
     *
     * @throws PatchConflictException if any two patches overlap
     * @throws YamlSyntaxException    if the patched source has syntax errors
     */
    public function apply(YamlDocument $document, YamlTreeSitterCore $core, array $patches): YamlDocument
    {
        $this->conflictChecker->assertNonOverlapping($patches);
        $patchedSource = $this->applyPatches($document->source, $patches);

        return $this->parser->parse($patchedSource, $core);
    }

    /**
     * Applies patches from end to start so that earlier byte offsets do not drift.
     *
     * Time complexity is O(n × m) where n = number of patches and m = source length, because
     * each patch allocates a new string. For typical use (1–10 patches) this is negligible.
     * If you need to apply hundreds of patches in a hot path, batch upstream into fewer, larger
     * replacements before calling {@see apply()}.
     *
     * @param list<YamlPatch> $patches
     */
    private function applyPatches(string $source, array $patches): string
    {
        $sorted = $patches;
        usort(
            $sorted,
            static fn (YamlPatch $patch1, YamlPatch $patch2) => $patch2->span->startByte <=> $patch1->span->startByte,
        );

        $result = $source;

        foreach ($sorted as $patch) {
            $result = substr($result, 0, $patch->span->startByte)
                . $patch->replacement
                . substr($result, $patch->span->endByte);
        }

        return $result;
    }
}
