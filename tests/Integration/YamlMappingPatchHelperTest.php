<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration;

use LogicException;
use Mougrim\YamlCst\Helper\YamlMappingPatchHelper;
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(YamlMappingPatchHelper::class)]
final class YamlMappingPatchHelperTest extends IntegrationTestCase
{
    private YamlMappingPatchHelper $patchHelper;

    protected function setUp(): void
    {
        $this->patchHelper = new YamlMappingPatchHelper(
            textStyleHelper: new YamlTextStyleHelper(),
        );
    }

    public function testDeletionPatchRemovesEntireLine(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            name: mydb
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->deletionPatch($doc->source, $doc->index->get(['port']));
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame(
            <<<'YAML'
                host: localhost
                name: mydb
                YAML,
            $updated->source,
        );
    }

    public function testDeletionPatchRemovesNestedEntry(): void
    {
        $yaml = <<<'YAML'
            database:
              host: localhost
              port: 5432
              name: mydb
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->deletionPatch($doc->source, $doc->index->get(['database', 'port']));
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame(
            <<<'YAML'
                database:
                  host: localhost
                  name: mydb
                YAML,
            $updated->source,
        );
    }

    public function testInsertionPatchAddsEntryAfterPair(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->insertionPatch($doc->source, $doc->index->get(['port']), 'name: mydb');
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame(
            <<<'YAML'
                host: localhost
                port: 5432
                name: mydb
                YAML,
            $updated->source,
        );
    }

    public function testInsertionPatchMatchesNestedIndent(): void
    {
        $yaml = <<<'YAML'
            database:
              host: localhost
              port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->insertionPatch(
            $doc->source,
            $doc->index->get(['database', 'port']),
            'name: mydb',
        );
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame(
            <<<'YAML'
                database:
                  host: localhost
                  port: 5432
                  name: mydb
                YAML,
            $updated->source,
        );
    }

    public function testReplacementPatchReplacesValue(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->replacementPatch($doc->index->get(['host']), 'production.db');
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame(
            <<<'YAML'
                host: production.db
                port: 5432
                YAML,
            $updated->source,
        );
    }

    public function testReplacementPatchThrowsForKeyOnlyPair(): void
    {
        $yaml = <<<'YAML'
            section:
              key:
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches("/key-only pair 'key'/");

        $this->patchHelper->replacementPatch($doc->index->get(['section', 'key']), 'value');
    }

    public function testDeletionPatchOnLastLineWithoutTrailingNewline(): void
    {
        // No trailing newline after the last entry.
        // The '\n' between lines belongs to the preceding line, so after deleting "port: 5432"
        // the separator newline remains, giving "host: localhost\n".
        $yaml = "host: localhost\nport: 5432";
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->deletionPatch($doc->source, $doc->index->get(['port']));
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame("host: localhost\n", $updated->source);
    }

    public function testInsertionPatchOnLastLineWithoutTrailingNewline(): void
    {
        // No trailing newline
        $yaml = "host: localhost\nport: 5432";
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->insertionPatch($doc->source, $doc->index->get(['port']), 'name: mydb');
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame("host: localhost\nport: 5432\nname: mydb", $updated->source);
    }

    public function testDeletionPatchWithCrlfLineEndings(): void
    {
        $yaml = "host: localhost\r\nport: 5432\r\nname: mydb\r\n";
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->deletionPatch($doc->source, $doc->index->get(['port']));
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame("host: localhost\r\nname: mydb\r\n", $updated->source);
    }

    public function testInsertionPatchWithCrlfLineEndings(): void
    {
        // No trailing CRLF — same convention as the heredoc-based tests.
        // insertionPatch prepends the detected EOL (\r\n) before the new entry.
        $yaml = "host: localhost\r\nport: 5432";
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $patch = $this->patchHelper->insertionPatch($doc->source, $doc->index->get(['port']), 'name: mydb');
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [$patch]);

        self::assertSame("host: localhost\r\nport: 5432\r\nname: mydb", $updated->source);
    }
}
