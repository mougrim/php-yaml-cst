<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Helper;

use LogicException;
use Mougrim\YamlCst\DomainModel\YamlMappingPairRef;
use Mougrim\YamlCst\DomainModel\YamlPatch;
use Mougrim\YamlCst\DomainModel\YamlSpan;
use Mougrim\YamlCst\YamlDocumentPatchApplier;

/**
 * High-level patch helpers for common key–value operations.
 *
 * Builds ready-to-use {@see YamlPatch} objects for the most frequent mutations:
 * replacing a value, deleting an existing entry, and inserting a new entry after an existing one.
 * Pass the returned patches to {@see YamlDocumentPatchApplier::apply()}.
 *
 * Usage example:
 *
 * ```php
 * use Mougrim\YamlCst\Helper\YamlMappingPatchHelper;
 *
 * $helper = new YamlMappingPatchHelper();
 *
 * // Replace a value
 * $patch = $helper->replacementPatch($document->index->get(['database', 'host']), 'production.db');
 * $updated = $applier->apply($document, $core, [$patch]);
 *
 * // Delete a key–value pair (including its line)
 * $patch = $helper->deletionPatch($document->source, $document->index->get(['database', 'password']));
 * $updated = $applier->apply($document, $core, [$patch]);
 *
 * // Insert a new key–value pair after an existing one (indent is matched automatically)
 * $patch = $helper->insertionPatch($document->source, $document->index->get(['database', 'port']), 'timeout: 30');
 * $updated = $applier->apply($document, $core, [$patch]);
 * ```
 */
readonly class YamlMappingPatchHelper
{
    public function __construct(
        private YamlTextStyleHelper $textStyleHelper,
    ) {
    }

    /**
     * Returns a patch that replaces the value of $pair with $newValue.
     *
     * This is the most common patching operation — use it when you want to change a scalar
     * value while leaving the key and surrounding formatting intact.
     *
     * @param string $newValue The raw replacement text (must be a valid YAML scalar in the
     *                         context of the document, e.g. `'production.db'` or `'"quoted"'`).
     *
     * @throws LogicException if $pair has no value node (key-only entry such as `key:`)
     */
    public function replacementPatch(YamlMappingPairRef $pair, string $newValue): YamlPatch
    {
        $valueSpan = $pair->valueSpan();

        if ($valueSpan === null) {
            throw new LogicException(
                "Cannot replace value of key-only pair '{$pair->keyText}' — it has no value node.",
            );
        }

        return new YamlPatch($valueSpan, $newValue);
    }

    /**
     * Returns a patch that deletes the full line(s) occupied by $pair.
     *
     * The span covers from the start of the line (including any leading indent) up to
     * and including the trailing newline character. This avoids leaving an empty line
     * or stray whitespace in the output.
     * If the pair is on the last line with no trailing newline, the span ends at EOF.
     */
    public function deletionPatch(string $source, YamlMappingPairRef $pair): YamlPatch
    {
        $pairSpan = $pair->pairNode->span();
        $startByte = $this->textStyleHelper->lineStart($source, $pairSpan->startByte);
        $endByte = $this->textStyleHelper->nextLineBreakEnd($source, $pairSpan->endByte);

        return new YamlPatch(new YamlSpan($startByte, $endByte), '');
    }

    /**
     * Returns a patch that inserts $newEntry as a new key–value line after $afterPair.
     *
     * The indent of $afterPair is detected automatically and prepended to $newEntry.
     * The correct end-of-line sequence is also prepended so the result is:
     * `<eol><indent><newEntry>`.
     *
     * The patch is a zero-length insertion (the span start equals end) placed at the
     * position immediately after the line that contains $afterPair.
     *
     * @param string $newEntry The raw text of the new entry, without leading indent or newline
     *                         (e.g. `'timeout: 30'` or `'"my key": value'`).
     */
    public function insertionPatch(string $source, YamlMappingPairRef $afterPair, string $newEntry): YamlPatch
    {
        $pairSpan = $afterPair->pairNode->span();
        $lineStart = $this->textStyleHelper->lineStart($source, $pairSpan->startByte);
        $indent = $this->textStyleHelper->indentOfLineTo($source, $lineStart, $pairSpan->startByte);
        $eol = $this->textStyleHelper->detectEndOfLine($source);
        $insertAt = $this->textStyleHelper->nextLineBreakEnd($source, $pairSpan->endByte);

        return new YamlPatch(
            new YamlSpan($insertAt, $insertAt),
            "{$eol}{$indent}{$newEntry}",
        );
    }
}
