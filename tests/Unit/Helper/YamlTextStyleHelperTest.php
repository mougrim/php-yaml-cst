<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Unit\Helper;

use Mougrim\YamlCst\Helper\YamlTextStyleHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlTextStyleHelper::class)]
final class YamlTextStyleHelperTest extends TestCase
{
    private YamlTextStyleHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new YamlTextStyleHelper();
    }

    /** @return array<string, array{source: string, expected: string}> */
    public static function detectEndOfLineProvider(): array
    {
        return [
            'unix newline' => [
                'source' => "foo\nbar\n",
                'expected' => "\n",
            ],
            'windows newline' => [
                'source' => "foo\r\nbar\r\n",
                'expected' => "\r\n",
            ],
            'no newline defaults to unix' => [
                'source' => 'no newline',
                'expected' => "\n",
            ],
        ];
    }

    #[DataProvider('detectEndOfLineProvider')]
    public function testDetectEndOfLine(string $source, string $expected): void
    {
        self::assertSame($expected, $this->helper->detectEndOfLine($source));
    }

    /** @return array<string, array{source: string, byteOffset: int, expected: int}> */
    public static function lineStartProvider(): array
    {
        return [
            'byte inside first word (no preceding newline)' => [
                'source' => "foo\nbar",
                'byteOffset' => 3,
                'expected' => 0,
            ],
            'start of second line' => [
                'source' => "foo\nbar",
                'byteOffset' => 4,
                'expected' => 4,
            ],
            'mid of second line' => [
                'source' => "foo\nbar",
                'byteOffset' => 6,
                'expected' => 4,
            ],
            'byte zero' => [
                'source' => 'hello',
                'byteOffset' => 0,
                'expected' => 0,
            ],
        ];
    }

    #[DataProvider('lineStartProvider')]
    public function testLineStart(string $source, int $byteOffset, int $expected): void
    {
        self::assertSame($expected, $this->helper->lineStart($source, $byteOffset));
    }

    /** @return array<string, array{source: string, lineStart: int, nodeStart: int, expected: string}> */
    public static function indentOfLineToProvider(): array
    {
        return [
            'two spaces indent' => [
                'source' => '  key: value',
                'lineStart' => 0,
                'nodeStart' => 2,
                'expected' => '  ',
            ],
            'no indent' => [
                'source' => 'key: value',
                'lineStart' => 0,
                'nodeStart' => 0,
                'expected' => '',
            ],
        ];
    }

    #[DataProvider('indentOfLineToProvider')]
    public function testIndentOfLineTo(string $source, int $lineStart, int $nodeStart, string $expected): void
    {
        self::assertSame($expected, $this->helper->indentOfLineTo($source, $lineStart, $nodeStart));
    }

    /** @return array<string, array{source: string, from: int, expected: int}> */
    public static function nextLineBreakEndProvider(): array
    {
        return [
            'empty string' => [
                'source' => '',
                'from' => 0,
                'expected' => 0,
            ],
            'single newline' => [
                'source' => "\n",
                'from' => 0,
                'expected' => 1,
            ],
            'newline at end' => [
                'source' => "hello\n",
                'from' => 0,
                'expected' => 6,
            ],
            'two lines first' => [
                'source' => "a\nb",
                'from' => 0,
                'expected' => 2,
            ],
            'two lines second' => [
                'source' => "a\nb",
                'from' => 2,
                'expected' => 3,
            ],
            'finds newline from start' => [
                'source' => "hello\nworld",
                'from' => 0,
                'expected' => 6,
            ],
            'finds newline from mid-line' => [
                'source' => "hello\nworld",
                'from' => 3,
                'expected' => 6,
            ],
            'no newline returns strlen' => [
                'source' => 'no newline here',
                'from' => 0,
                'expected' => 15,
            ],
            // "a\nb\nc": a=0,\n=1,b=2,\n=3,c=4 — searching from byte 3 (\n) returns 4
            'at newline returns position after it' => [
                'source' => "a\nb\nc",
                'from' => 3,
                'expected' => 4,
            ],
        ];
    }

    #[DataProvider('nextLineBreakEndProvider')]
    public function testNextLineBreakEnd(string $source, int $from, int $expected): void
    {
        self::assertSame($expected, $this->helper->nextLineBreakEnd($source, $from));
    }

    /** @return array<string, array{raw: string, expected: string}> */
    public static function normalizeScalarProvider(): array
    {
        return [
            'double-quoted value' => [
                'raw' => '"localhost"',
                'expected' => 'localhost',
            ],
            'single-quoted value' => [
                'raw' => "'localhost'",
                'expected' => 'localhost',
            ],
            'double-quoted with escaped double quote' => [
                'raw' => '"foo\"bar"',
                'expected' => 'foo"bar',
            ],
            'single-quoted with escaped single quote' => [
                'raw' => "'foo''bar'",
                'expected' => "foo'bar",
            ],
            'plain unquoted value' => [
                'raw' => 'localhost',
                'expected' => 'localhost',
            ],
            'value with leading and trailing spaces' => [
                'raw' => '  value  ',
                'expected' => 'value',
            ],
        ];
    }

    #[DataProvider('normalizeScalarProvider')]
    public function testNormalizeScalar(string $raw, string $expected): void
    {
        self::assertSame($expected, $this->helper->normalizeScalar($raw));
    }

    /** @return array<string, array{raw: string, expected: string}> */
    public static function fullyNormalizeScalarProvider(): array
    {
        return [
            'double-quoted plain string' => [
                'raw' => '"localhost"',
                'expected' => 'localhost',
            ],
            'single-quoted plain string' => [
                'raw' => "'localhost'",
                'expected' => 'localhost',
            ],
            'unquoted plain string' => [
                'raw' => 'localhost',
                'expected' => 'localhost',
            ],
            'double-quoted with \n' => [
                'raw' => '"hello\nworld"',
                'expected' => "hello\nworld",
            ],
            'double-quoted with \t' => [
                'raw' => '"hello\tworld"',
                'expected' => "hello\tworld",
            ],
            'double-quoted with \\\\' => [
                'raw' => '"back\\\slash"',
                'expected' => 'back\slash',
            ],
            'double-quoted with \"' => [
                'raw' => '"say \"hi\""',
                'expected' => 'say "hi"',
            ],
            'double-quoted with \r' => [
                'raw' => '"carriage\rreturn"',
                'expected' => "carriage\rreturn",
            ],
            'double-quoted with \xXX' => [
                'raw' => '"\x41"',
                'expected' => 'A',
            ],
            'double-quoted with \uXXXX' => [
                'raw' => '"\u0041"',
                'expected' => 'A',
            ],
            'double-quoted with \UXXXXXXXX' => [
                'raw' => '"\U00000041"',
                'expected' => 'A',
            ],
            'double-quoted with \0' => [
                'raw' => '"null\0char"',
                'expected' => "null\x00char",
            ],
            'single-quoted with escaped single quote' => [
                'raw' => "'foo''bar'",
                'expected' => "foo'bar",
            ],
            'double-quoted with \a (bell)' => [
                'raw' => '"\a"',
                'expected' => "\x07",
            ],
            'double-quoted with \v (vertical tab)' => [
                'raw' => '"\v"',
                'expected' => "\x0B",
            ],
            'double-quoted with \e (escape)' => [
                'raw' => '"\e"',
                'expected' => "\x1B",
            ],
            'double-quoted with \b (backspace)' => [
                'raw' => '"\b"',
                'expected' => "\x08",
            ],
            'double-quoted with \f (form feed)' => [
                'raw' => '"\f"',
                'expected' => "\x0C",
            ],
            'double-quoted with \/ (slash)' => [
                'raw' => '"\/"',
                'expected' => '/',
            ],
            'double-quoted with \N (U+0085 NEXT LINE)' => [
                'raw' => '"\N"',
                'expected' => "\xC2\x85",
            ],
            'double-quoted with \_ (U+00A0 NO-BREAK SPACE)' => [
                'raw' => '"\_"',
                'expected' => "\xC2\xA0",
            ],
            'double-quoted with \L (U+2028 LINE SEPARATOR)' => [
                'raw' => '"\L"',
                'expected' => "\xE2\x80\xA8",
            ],
            'double-quoted with \P (U+2029 PARAGRAPH SEPARATOR)' => [
                'raw' => '"\P"',
                'expected' => "\xE2\x80\xA9",
            ],
            'double-quoted with unknown escape sequence is left unchanged' => [
                // \z is not a YAML escape — the regex does not match it, so it stays verbatim.
                'raw' => '"hello\zworld"',
                'expected' => 'hello\zworld',
            ],
        ];
    }

    #[DataProvider('fullyNormalizeScalarProvider')]
    public function testFullyNormalizeScalar(string $raw, string $expected): void
    {
        self::assertSame($expected, $this->helper->fullyNormalizeScalar($raw));
    }
}
