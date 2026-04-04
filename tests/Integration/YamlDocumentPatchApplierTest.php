<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Tests\Integration;

use Mougrim\YamlCst\DomainModel\YamlSpan;
use Mougrim\YamlCst\Exception\PatchConflictException;
use Mougrim\YamlCst\Tests\FixtureBuilder\DomainModel\YamlPatchFixtureBuilder;
use Mougrim\YamlCst\Tests\IntegrationTestCase;
use Mougrim\YamlCst\YamlDocumentPatchApplier;
use PHPUnit\Framework\Attributes\CoversClass;

use function substr;

#[CoversClass(YamlDocumentPatchApplier::class)]
final class YamlDocumentPatchApplierTest extends IntegrationTestCase
{
    public function testApplySingleValueReplacement(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $hostValueSpan = $doc->index->get(['host'])->valueSpan();
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(span: $hostValueSpan, replacement: 'production.db'),
        ]);

        self::assertSame(
            <<<'YAML'
                host: production.db
                YAML,
            $updated->source,
        );
    }

    public function testApplyMultiplePatches(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $hostValueSpan = $doc->index->get(['host'])->valueSpan();
        $portValueSpan = $doc->index->get(['port'])->valueSpan();
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(span: $hostValueSpan, replacement: 'db.example.com'),
            new YamlPatchFixtureBuilder()->build(span: $portValueSpan, replacement: '3306'),
        ]);

        self::assertSame(
            <<<'YAML'
                host: db.example.com
                port: 3306
                YAML,
            $updated->source,
        );
    }

    public function testApplyReparsesUpdatedDocument(): void
    {
        $yaml = <<<'YAML'
            name: old
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $nameValueSpan = $doc->index->get(['name'])->valueSpan();
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(span: $nameValueSpan, replacement: 'new'),
        ]);

        $newValueSpan = $updated->index->get(['name'])->valueSpan();
        $newValueText = $newValueSpan !== null
            ? substr($updated->source, $newValueSpan->startByte, $newValueSpan->length())
            : null;

        self::assertSame('new', $newValueText);
    }

    public function testApplyNoPatchesReturnsSameContent(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $updated = $this->makeApplier()->apply($doc, self::getCore(), []);

        self::assertSame($yaml, $updated->source);
    }

    public function testApplyWithConflictingPatchesThrows(): void
    {
        $yaml = <<<'YAML'
            key: value
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $valueSpan = $doc->index->get(['key'])->valueSpan();

        $this->expectException(PatchConflictException::class);

        $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(span: $valueSpan, replacement: 'first'),
            new YamlPatchFixtureBuilder()->build(span: $valueSpan, replacement: 'second'),
        ]);
    }

    public function testApplyPreservesIndentation(): void
    {
        $yaml = <<<'YAML'
            database:
              host: localhost
              port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $hostValueSpan = $doc->index->get(['database', 'host'])->valueSpan();
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(span: $hostValueSpan, replacement: 'production.db'),
        ]);

        self::assertSame(
            <<<'YAML'
                database:
                  host: production.db
                  port: 5432
                YAML,
            $updated->source,
        );
    }

    public function testApplyDeletion(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            name: mydb
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $pairSpan = $doc->index->get(['port'])->pairNode->span();
        // Delete the entire "port" line including its trailing newline
        $endByte = $pairSpan->endByte + 1; // +1 for '\n'
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(
                span: new YamlSpan($pairSpan->startByte, $endByte),
                replacement: '',
            ),
        ]);

        self::assertSame(
            <<<'YAML'
                host: localhost
                name: mydb
                YAML,
            $updated->source,
        );
    }

    public function testApplyInsertion(): void
    {
        $yaml = <<<'YAML'
            host: localhost
            port: 5432
            YAML;
        $doc = $this->makeParser()->parse($yaml, self::getCore());
        $portPairEndByte = $doc->index->get(['port'])->pairNode->span()->endByte;
        // Insert after the "port" line — the source ends without a trailing newline here,
        // so we append directly at the end
        $updated = $this->makeApplier()->apply($doc, self::getCore(), [
            new YamlPatchFixtureBuilder()->build(
                span: new YamlSpan($portPairEndByte, $portPairEndByte),
                replacement: "\nname: mydb",
            ),
        ]);

        self::assertSame(
            <<<'YAML'
                host: localhost
                port: 5432
                name: mydb
                YAML,
            $updated->source,
        );
    }
}
