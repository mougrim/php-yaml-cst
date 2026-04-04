<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Helper;

use Mougrim\YamlCst\YamlIndexBuilder;

use function chr;
use function hexdec;
use function mb_chr;
use function preg_replace_callback;
use function str_contains;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

readonly class YamlTextStyleHelper
{
    public function detectEndOfLine(string $source): string
    {
        return str_contains($source, "\r\n") ? "\r\n" : "\n";
    }

    /** Returns byte offset of the line start (after the previous \n) */
    public function lineStart(string $source, int $byteOffset): int
    {
        if ($byteOffset === 0) {
            return 0;
        }
        // strrpos with a negative $offset counts backwards from the end of $source.
        // We want to search for a '\n' that comes before $byteOffset, so we need to
        // start the search at position ($byteOffset - 1). Translating that into a
        // negative offset: ($byteOffset - 1) - strlen($source).
        $position = strrpos($source, "\n", ($byteOffset - 1) - strlen($source));

        return $position === false ? 0 : $position + 1;
    }

    /**
     * Returns the raw substring from $lineStart up to (but not including) $toByte.
     *
     * Typically used to extract the leading whitespace (indent) before a key:
     * pass the byte offset of the line start and the byte offset of the key as $toByte.
     * The result is the indent string as it literally appears in the source.
     */
    public function indentOfLineTo(string $source, int $lineStart, int $toByte): string
    {
        return substr($source, $lineStart, $toByte - $lineStart);
    }

    /** Finds the end of line (position after '\n' or end of file) starting from offset */
    public function nextLineBreakEnd(string $source, int $fromByte): int
    {
        $position = strpos($source, "\n", $fromByte);

        return $position === false ? strlen($source) : $position + 1;
    }

    /**
     * Strips surrounding quotes from a YAML scalar and applies minimal unescaping.
     *
     * This is NOT full YAML parsing — it handles only the most common case of
     * plain or simply-quoted strings in typical config YAML.
     *
     * Handled escape sequences:
     * - `\"` → `"` inside double-quoted scalars
     * - `''` → `'` inside single-quoted scalars
     *
     * Other YAML escape sequences (`\n`, `\t`, `\\`, `\uXXXX`, etc.) are NOT unescaped.
     * For full escape handling use {@see fullyNormalizeScalar()}.
     * For full YAML parsing, use a dedicated YAML parser.
     *
     * Used internally by {@see YamlIndexBuilder} for key normalization.
     */
    public function normalizeScalar(string $raw): string
    {
        $trimmed = trim($raw, " \t");

        if (strlen($trimmed) >= 2) {
            $quote = $trimmed[0];
            $inner = substr($trimmed, 1, -1);

            if ($quote === '"' && $trimmed[strlen($trimmed) - 1] === '"') {
                return str_replace('\"', '"', $inner);
            }

            if ($quote === "'" && $trimmed[strlen($trimmed) - 1] === "'") {
                return str_replace("''", "'", $inner);
            }
        }

        return $trimmed;
    }

    /**
     * Strips surrounding quotes and fully unescapes a YAML scalar.
     *
     * For double-quoted scalars, all YAML 1.2 escape sequences are resolved:
     * `\n`, `\t`, `\\`, `\"`, `\/`, `\b`, `\f`, `\r`, `\a`, `\v`, `\e`, `\0`,
     * `\N` (U+0085), `\_` (U+00A0), `\L` (U+2028), `\P` (U+2029),
     * `\xXX`, `\uXXXX`, `\UXXXXXXXX`.
     *
     * For single-quoted scalars, only `''` → `'` is applied (per YAML spec).
     * For plain (unquoted) scalars, only leading/trailing whitespace is trimmed.
     *
     * For complete YAML value semantics (multi-line folding, flow scalars, etc.)
     * use a full YAML parser.
     */
    public function fullyNormalizeScalar(string $raw): string
    {
        $trimmed = trim($raw, " \t");

        if (strlen($trimmed) >= 2) {
            $quote = $trimmed[0];
            $last = $trimmed[strlen($trimmed) - 1];
            $inner = substr($trimmed, 1, -1);

            if ($quote === '"' && $last === '"') {
                return $this->unescapeDoubleQuoted($inner);
            }

            if ($quote === "'" && $last === "'") {
                return str_replace("''", "'", $inner);
            }
        }

        return $trimmed;
    }

    /**
     * Applies all YAML 1.2 double-quoted escape sequences to the string content
     * (the part between the surrounding `"…"` quotes, not including the quotes themselves).
     *
     * The regex matches only the sequences listed in the match expression; any other `\X`
     * combination is not matched and therefore left unchanged in the output. The `default`
     * branch is unreachable given the regex and exists only as a safety net.
     */
    private function unescapeDoubleQuoted(string $inner): string
    {
        return preg_replace_callback(
            '/\\\(["\\\\\/bfnrtvae0NL_P]|x[0-9a-fA-F]{2}|u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8})/',
            static function (array $matches): string {
                $seq = $matches[1];

                return match ($seq[0]) {
                    '"' => '"',
                    '\\' => '\\',
                    '/' => '/',
                    'b' => "\x08",
                    'f' => "\x0C",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'v' => "\x0B",
                    'a' => "\x07",
                    'e' => "\x1B",
                    '0' => "\x00",
                    'N' => "\xC2\x85",        // U+0085 NEXT LINE (UTF-8)
                    '_' => "\xC2\xA0",        // U+00A0 NO-BREAK SPACE (UTF-8)
                    'L' => "\xE2\x80\xA8",    // U+2028 LINE SEPARATOR (UTF-8)
                    'P' => "\xE2\x80\xA9",    // U+2029 PARAGRAPH SEPARATOR (UTF-8)
                    'x' => chr((int) hexdec(substr($seq, 1))),
                    'u', 'U' => (mb_chr((int) hexdec(substr($seq, 1)), 'UTF-8') ?: ''),
                    default => $matches[0],
                };
            },
            $inner,
        ) ?? $inner;
    }
}
