# mougrim/yaml-cst

[![CI](https://github.com/mougrim/php-yaml-cst/actions/workflows/ci.yml/badge.svg)](https://github.com/mougrim/php-yaml-cst/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

A PHP library for parsing and manipulating YAML documents while preserving format using [Tree-sitter](https://tree-sitter.github.io/tree-sitter/) via FFI (Foreign Function Interface).

It exposes a **Concrete Syntax Tree (CST)** — every byte of the original source is accounted for, including whitespace and comments — and provides a high-level API for:

- **Path-based lookups** — navigate to any mapping pair with a dotted path like `database.host`.
- **Non-destructive patching** — queue text replacements and apply them all at once without re-formatting the rest of the file.
- **Precise error reporting** — syntax errors include the exact line and byte offset.
- **Line/column mapping** — convert any byte offset to a human-readable line and column.

> **Note:** Path-based access via `YamlIndex` is limited to **mapping pairs** (key–value entries).
> Sequences (YAML arrays) are not indexed by path. To traverse sequences or other non-mapping
> constructs, use `YamlDocument::$tree` and the `YamlCstNodeRef` API directly.

> **Note:** Multi-document YAML files (with `---` document separators) are not supported.
> `YamlCstParser::parse()` expects a single YAML document. Passing a multi-document file
> may produce unexpected results or a syntax error.

> **Note:** Parsing an empty string (`''`) is valid and produces a `YamlDocument` with an
> empty index. `YamlDocument::isEmpty()` returns `true` for empty strings and for documents
> containing only comments.

## Requirements

| Requirement        | Version                              |
|--------------------|--------------------------------------|
| PHP                | ≥ 8.4                                |
| `ext-ffi`          | any (enabled with `ffi.enable=true`) |
| `libtree-sitter`   | ≥ 0.26.8                             |
| `tree-sitter-yaml` | ≥ 0.7.2                              |

The native `.so` libraries must be present on the system. The included [Dockerfile](#docker) builds and installs them automatically.

## Quick start

```php
use Mougrim\YamlCst\DomainModel\YamlPatch;
use Mougrim\YamlCst\Factory\YamlLineMapFactory;
use Mougrim\YamlCst\Factory\YamlSyntaxExceptionFactory;
use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;
use Mougrim\YamlCst\YamlCstParser;
use Mougrim\YamlCst\YamlDocumentPatchApplier;
use Mougrim\YamlCst\YamlIndexBuilder;
use Mougrim\YamlCst\YamlPatchConflictChecker;

$lineMapFactory = new YamlLineMapFactory();
$core = (new YamlTreeSitterCoreFactory())->create(); // create once per process
$parser = new YamlCstParser(
    $lineMapFactory,
    new YamlIndexBuilder(new YamlTextStyleHelper()),
    new YamlSyntaxExceptionFactory($lineMapFactory),
);
$applier = new YamlDocumentPatchApplier($parser, new YamlPatchConflictChecker());

$yaml = "database:\n  host: localhost\n  port: 5432\n";
$document = $parser->parse($yaml, $core);

// Read a value
echo $document->index->get(['database', 'host'])->valueText($yaml); // localhost

// Patch a value and get a new document
$span = $document->index->get(['database', 'host'])->valueSpan();
$updated = $applier->apply($document, $core, [new YamlPatch($span, 'production.db')]);
echo $updated->source; // database:\n  host: production.db\n  port: 5432\n
```

> See [Setting up the parser](#setting-up-the-parser) for detailed construction notes and DI
> container recommendations.

## Installation

```bash
composer require mougrim/yaml-cst
```

### Building the native libraries

The library uses tree-sitter's C API through PHP FFI. You need two shared libraries:

- `libtree-sitter.so` — core tree-sitter runtime
- `libtree-sitter-yaml.so` — YAML grammar

#### Option A — Docker (recommended)

The provided `Dockerfile` compiles both libraries from source:

```bash
docker build --target runtime -t mougrim/yaml-cst-dev .
```

#### Option B — Build manually

```bash
# 1. Install tree-sitter CLI (requires Rust/cargo)
cargo install --locked tree-sitter-cli --version 0.26.8

# 2. Build and install libtree-sitter
git clone --depth 1 --branch v0.26.8 https://github.com/tree-sitter/tree-sitter.git
make -C tree-sitter && make -C tree-sitter install PREFIX=/usr/local

# 3. Build and install tree-sitter-yaml
git clone --depth 1 --branch v0.7.2 https://github.com/tree-sitter-grammars/tree-sitter-yaml.git
cd tree-sitter-yaml && make YAML_SCHEMA=core && make install PREFIX=/usr/local
```

Then enable FFI in `php.ini`:

```ini
ffi.enable = true
```

## Usage

### Setting up the parser

It is recommended to create the parser and its dependencies using a **dependency-injection (DI)
container** so that all collaborators are wired and shared automatically. The example below shows
manual construction for illustration purposes.

The `YamlTreeSitterCore` instance wraps the native FFI bindings and is expensive to initialise.
Create it **once per process** (e.g. as a DI singleton) and reuse it for every `parse()` call.

```php
use Mougrim\YamlCst\Factory\YamlLineMapFactory;
use Mougrim\YamlCst\Factory\YamlSyntaxExceptionFactory;
use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;
use Mougrim\YamlCst\YamlCstParser;
use Mougrim\YamlCst\YamlDocumentPatchApplier;
use Mougrim\YamlCst\YamlIndexBuilder;
use Mougrim\YamlCst\YamlPatchConflictChecker;

// Create the native core once — reuse it for every parse() call.
$core = (new YamlTreeSitterCoreFactory())->create();

$lineMapFactory = new YamlLineMapFactory();
$parser = new YamlCstParser(
    $lineMapFactory,
    new YamlIndexBuilder(
        textStyleHelper: new YamlTextStyleHelper(),
    ),
    new YamlSyntaxExceptionFactory($lineMapFactory),
);

$conflictChecker = new YamlPatchConflictChecker();
$applier = new YamlDocumentPatchApplier($parser, $conflictChecker);
```

### Parsing a YAML document

```php
$yaml = <<<YAML
database:
  host: localhost
  port: 5432
  credentials:
    user: app
    password: secret
YAML;

$document = $parser->parse($yaml, $core);
```

### Path-based access

> **Limitation:** `YamlIndex` only indexes **mapping pairs** (key–value entries).
> Sequences (YAML lists) are not accessible by path — traverse them via
> `YamlDocument::$tree` and `YamlCstNodeRef` instead.

> **Key normalization:** Quoted keys are stripped of their surrounding quotes and only the
> following escape sequences are unescaped: `\"` inside double-quoted keys, and `''` inside
> single-quoted keys. Other YAML escape sequences (`\n`, `\t`, `\\`, `\uXXXX`, etc.) are
> **not** unescaped. Keys with such sequences will be stored with the literal backslash in the
> path index.

> **Dot-collision:** If a YAML file contains a quoted key whose text includes a literal `.`
> (e.g. `"foo.bar": value`), the dot-path convenience methods (`getByPath`, `hasByPath`, etc.)
> cannot distinguish it from a nested key `foo → bar`. Use the **primary segment-based API**
> to be unambiguous: `get(['foo.bar'])` is one key named `foo.bar`; `get(['foo', 'bar'])` is a
> nested key `bar` inside `foo`.

The primary API uses `list<string>` segments. Dot-path strings are available as convenience
wrappers (`getByPath`, `hasByPath`, `findByPath`, `childrenOfByPath`) for simple cases where
key names are known not to contain dots.

```php
// Primary API — segment-based, unambiguous
$hostPair = $document->index->get(['database', 'host']);

echo $hostPair->keyText;  // "host"

// Extract the value text using its byte span
$valueSpan = $hostPair->valueSpan();
echo substr($yaml, $valueSpan->startByte, $valueSpan->length());
// "localhost"

// Check whether a path exists before accessing it
if ($document->index->has(['database', 'port'])) {
    $portPair = $document->index->get(['database', 'port']);
}

// Non-throwing lookup — returns null when not found
$portPair = $document->index->find(['database', 'port']);
if ($portPair !== null) {
    // ...
}

// Get the raw value text directly (convenience method)
$hostText = $document->index->get(['database', 'host'])->valueText($yaml);
// "localhost"

// List direct children of a parent segment path
$dbChildren = $document->index->childrenOf(['database']);
foreach ($dbChildren as $pair) {
    echo $pair->keyText . "\n";
    // host, port, credentials
}

// Lookup a key whose name contains a literal dot — unambiguous with segments
$dotKeyPair = $document->index->find(['my.key']); // key literally named "my.key"

// All indexed segment paths in document order: [['database'], ['database','host'], ...]
$paths = $document->index->allPaths();

// Convenience: dot-joined path strings (fine when key names don't contain dots)
$dotPaths = $document->index->allDotPaths(); // ['database', 'database.host', ...]
$hostPair = $document->index->getByPath('database.host');
```

### Patching a document

Patches are text replacements applied atomically. The original document is never mutated.

```php
use Mougrim\YamlCst\DomainModel\YamlPatch;

$valueSpan = $document->index->get(['database', 'host'])->valueSpan();

// Collect patches and pass them to apply() — returns a new, re-parsed document
// $applier is created in the setup section above
$updated = $applier->apply($document, $core, [
    new YamlPatch($valueSpan, 'production.db.example.com'),
]);

echo $updated->source;
// database:
//   host: production.db.example.com
//   port: 5432
//   ...
```

Applying overlapping patches throws `PatchConflictException`. The exception exposes
`$previousSpan` and `$currentSpan` so you can inspect which patches conflict.

### Traversing sequences with YamlCstSearcher

`YamlIndex` only covers mapping pairs. To work with sequences (YAML arrays) or to navigate
the raw CST, use `YamlCstSearcher` together with `YamlDocument::$tree`.

```php
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\YamlCstSearcher;

$yaml = <<<YAML
servers:
  - host: web1
    port: 80
  - host: web2
    port: 80
YAML;

$document = $parser->parse($yaml, $core);
$searcher = new YamlCstSearcher();

// Find the first BLOCK_MAPPING_PAIR anywhere in the tree
$firstPair = $searcher->firstDescendantOfType(
    $document->tree->root(),
    YamlNodeType::BLOCK_MAPPING_PAIR,
);

// Iterate the direct BLOCK_MAPPING_PAIR children of a block-mapping node
$blockMapping = $searcher->firstDescendantOfType(
    $document->tree->root(),
    YamlNodeType::BLOCK_MAPPING,
);

if ($blockMapping !== null) {
    foreach ($searcher->directMappingPairs($blockMapping) as $pair) {
        $keyNode = $pair->childByField(\Mougrim\YamlCst\Enum\YamlNodeField::KEY);
        echo $keyNode->text($yaml) . "\n"; // "servers"
    }
}
```

```php
// Iterate items in a block sequence using the convenience helper
$blockSequence = $searcher->firstDescendantOfType(
    $document->tree->root(),
    YamlNodeType::BLOCK_SEQUENCE,
);

if ($blockSequence !== null) {
    foreach ($searcher->directSequenceItems($blockSequence) as $item) {
        // $item is a block_sequence_item node
        echo $item->type()?->value . "\n";
    }
}
```

```php
// Skip comment nodes during traversal
foreach ($node->namedChildren() as $child) {
    if ($child->isExtra()) {
        continue; // skip comments and other extras
    }
    // process $child
}
```

```php
// Find ALL block-mapping pairs in the tree (not just the first)
$allPairs = $searcher->allDescendantsOfType(
    $document->tree->root(),
    YamlNodeType::BLOCK_MAPPING_PAIR,
);
```

```php
// Navigate to a sibling node
$node = $searcher->firstDescendantOfType($document->tree->root(), YamlNodeType::BLOCK_MAPPING_PAIR);

if ($node !== null) {
    $next = $node->nextNamedSibling();
    if (!$next->isNull()) {
        echo $next->type()?->value . "\n";
    }

    $prev = $node->previousNamedSibling();
    if (!$prev->isNull()) {
        echo $prev->type()?->value . "\n";
    }
}
```

### Common recipes

#### Reading a value

```php
// Using valueText() — the shortest form
$host = $document->index->get(['database', 'host'])->valueText($yaml);

// Equivalent using spans
$span = $document->index->get(['database', 'host'])->valueSpan();
$host = $span !== null ? substr($yaml, $span->startByte, $span->length()) : null;
```

> **Note:** `valueText()` returns the raw source text as-is, including any YAML quoting
> (e.g. `"localhost"` not `localhost`). To strip surrounding quotes use either:
> - `YamlTextStyleHelper::normalizeScalar()` — minimal unescaping (`\"` and `''` only)
> - `YamlTextStyleHelper::fullyNormalizeScalar()` — all YAML 1.2 escape sequences

```php
$helper = new YamlTextStyleHelper();
$rawValue = $document->index->get(['database', 'host'])->valueText($yaml); // '"localhost"'

// Minimal unescaping (only \" and '' are handled)
$value = $helper->normalizeScalar($rawValue); // 'localhost'

// Full YAML 1.2 unescaping (\n, \t, \\, \uXXXX, etc.)
$fullyUnescaped = $helper->fullyNormalizeScalar($rawValue); // 'localhost'
```

#### Reading the raw pair text

`pairText()` returns the full source text of a mapping pair (key + colon + value), useful for
debugging or when you need the raw representation:

```php
$rawPair = $document->index->get(['database', 'host'])->pairText($yaml);
// "host: localhost"
```

#### Replacing a value

The shortest form uses `YamlMappingPatchHelper::replacementPatch()`:

```php
use Mougrim\YamlCst\Helper\YamlMappingPatchHelper;

$patchHelper = new YamlMappingPatchHelper(
    textStyleHelper: new YamlTextStyleHelper(),
);
$updated = $applier->apply($document, $core, [
    $patchHelper->replacementPatch(
        $document->index->get(['database', 'host']),
        'production.db.example.com',
    ),
]);
```

Alternatively, build the patch manually from the value span:

```php
use Mougrim\YamlCst\DomainModel\YamlPatch;

$valueSpan = $document->index->get(['database', 'host'])->valueSpan();

$updated = $applier->apply($document, $core, [
    new YamlPatch($valueSpan, 'production.db.example.com'),
]);
```

#### Deleting a key–value pair

`YamlMappingPatchHelper` handles the span calculation automatically — it covers the full line
including the trailing newline so no empty line is left behind:

```php
use Mougrim\YamlCst\Helper\YamlMappingPatchHelper;

$patchHelper = new YamlMappingPatchHelper(
    textStyleHelper: new YamlTextStyleHelper(),
);
$patch = $patchHelper->deletionPatch(
    $document->source,
    $document->index->get(['database', 'password']),
);

$updated = $applier->apply($document, $core, [$patch]);
```

#### Inserting a new key–value pair

`YamlMappingPatchHelper::insertionPatch()` matches the indent and end-of-line sequence of the
reference pair automatically:

```php
use Mougrim\YamlCst\Helper\YamlMappingPatchHelper;

$patchHelper = new YamlMappingPatchHelper(
    textStyleHelper: new YamlTextStyleHelper(),
);
$patch = $patchHelper->insertionPatch(
    $document->source,
    $document->index->get(['database', 'port']),
    'timeout: 30',
);

$updated = $applier->apply($document, $core, [$patch]);
```

### Error handling

```php
use Mougrim\YamlCst\Exception\PathNotFoundException;
use Mougrim\YamlCst\Exception\PatchConflictException;
use Mougrim\YamlCst\Exception\YamlSyntaxException;

try {
    $document = $parser->parse($invalidYaml, $core);
} catch (YamlSyntaxException $e) {
    // "YAML syntax error at line 3 (byte 42): ..."
    echo $e->getMessage();
}

try {
    $document->index->get(['nonexistent', 'path']);
} catch (PathNotFoundException $e) {
    echo $e->getMessage();  // "Path not found: nonexistent.path"
}
```

### Line/column mapping

```php
$span = $document->index->get(['database', 'host'])->keySpan();
$location = $document->lineMap->locate($span->startByte);

echo "Line {$location->line}, column {$location->col}";
```

### Text-style helpers

`YamlTextStyleHelper` provides low-level utilities for working with raw YAML source text.
These are useful when building patches that must preserve the original formatting style.

```php
use Mougrim\YamlCst\Helper\YamlTextStyleHelper;

$helper = new YamlTextStyleHelper();

// Detect the end-of-line sequence used in the source ("\r\n" or "\n")
$eol = $helper->detectEndOfLine($yaml);

// Find the byte offset where the line containing $byteOffset starts
$lineStart = $helper->lineStart($yaml, $byteOffset);

// Extract the leading whitespace (indent) before a given byte offset
$indent = $helper->indentOfLineTo($yaml, $lineStart, $byteOffset);

// Find the byte offset after the next newline (useful for line-level patching)
$nextLine = $helper->nextLineBreakEnd($yaml, $byteOffset);

// Fully unescape a double-quoted YAML scalar (all YAML 1.2 escape sequences)
$resolved = $helper->fullyNormalizeScalar('"hello\\nworld"'); // "hello\nworld"
```

### Custom library paths

If the `.so` files are not in `/usr/local/lib`, pass explicit paths to the factory:

```php
$core = (new YamlTreeSitterCoreFactory())->create(
    coreLibPath: '/opt/lib/libtree-sitter.so',
    yamlLibPath: '/opt/lib/libtree-sitter-yaml.so',
);
```

## Docker

A `Dockerfile` is included for a fully reproducible environment.

```bash
# Build the image (compiles native libraries)
make build

# Install PHP dependencies
make install

# Run all tests
make test

# Run static analysis
make phpstan
```

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full guide.

Quick start:

```bash
make ci      # build → install → test → phpstan → cs-check
```

Available `make` targets:

| Target             | Description                                                          |
|--------------------|----------------------------------------------------------------------|
| `build`            | Build Docker image                                                   |
| `install`          | Install Composer dependencies (Docker)                               |
| `test`             | Run PHPUnit (Docker)                                                 |
| `test-unit`        | Run unit tests only (Docker)                                         |
| `test-integration` | Run integration tests only (Docker)                                  |
| `test-coverage`    | Run PHPUnit with HTML + text coverage report (Docker, requires pcov) |
| `phpstan`          | Run PHPStan at level max (Docker)                                    |
| `cs-check`         | Check code style with php-cs-fixer (Docker)                          |
| `cs-fix`           | Auto-fix code style with php-cs-fixer (Docker)                       |
| `clean`            | Remove local build artifacts (vendor, caches)                        |
| `bash`             | Open an interactive bash shell in Docker                             |
| `ci`               | Full pipeline: build → install → test → phpstan → cs-check           |

## Node types reference

`YamlNodeType` is the vocabulary you need when calling `YamlCstSearcher` methods or checking
`YamlCstNodeRef::type()`. The most common cases:

| `YamlNodeType` case    | tree-sitter string       | When you'll use it                                                       |
|------------------------|--------------------------|--------------------------------------------------------------------------|
| `BLOCK_MAPPING`        | `block_mapping`          | Root of an indented key–value block (`key: value`)                       |
| `BLOCK_MAPPING_PAIR`   | `block_mapping_pair`     | A single `key: value` entry inside a block mapping                       |
| `FLOW_MAPPING`         | `flow_mapping`           | Inline mapping (`{key: value}`)                                          |
| `FLOW_PAIR`            | `flow_pair`              | A single `key: value` entry inside a flow mapping                        |
| `BLOCK_SEQUENCE`       | `block_sequence`         | Root of a dash-list block (`- item`)                                     |
| `BLOCK_SEQUENCE_ITEM`  | `block_sequence_item`    | A single `- item` entry inside a block sequence                          |
| `PLAIN_SCALAR`         | `plain_scalar`           | An unquoted scalar value                                                 |
| `SINGLE_QUOTE_SCALAR`  | `single_quote_scalar`    | A `'single-quoted'` scalar                                               |
| `DOUBLE_QUOTE_SCALAR`  | `double_quote_scalar`    | A `"double-quoted"` scalar                                               |
| `BLOCK_SCALAR`         | `block_scalar`           | A literal (`\|`) or folded (`>`) block scalar                            |
| `COMMENT`              | `comment`                | A `# comment` node (also reported by `isExtra()`)                        |
| `ERROR`                | `ERROR`                  | A syntax-error node; triggers `YamlSyntaxException` from `YamlCstParser` |

The full list is in `src/Enum/YamlNodeType.php`. `YamlNodeType::tryFrom(string)` returns `null`
for any type string not in the enum (future grammar additions).

---

## API overview

It is recommended to create all objects via a **DI container** with auto-wiring.
For manual construction see the [Setting up the parser](#setting-up-the-parser) example above.

### Core classes

| Class                       | Description                                                                                                                                                                  |
|-----------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `YamlCstParser`             | Entry point — parses a YAML string into a `YamlDocument`.                                                                                                                    |
| `YamlDocumentPatchApplier`  | Accepts a `list<YamlPatch>` and a document, applies all patches, and returns a new re-parsed document.                                                                       |
| `YamlPatchConflictChecker`  | Validates that a list of `YamlPatch` objects do not overlap; throws `PatchConflictException` on conflict.                                                                    |
| `YamlCstSearcher`           | Tree-navigation helpers: `firstDescendantOfType()`, `allDescendantsOfType()`, `directMappingPairs()`, `directSequenceItems()`.                                               |
| `YamlTextStyleHelper`       | Low-level text utilities: EOL detection, indent extraction, line offsets, `normalizeScalar()` (minimal), `fullyNormalizeScalar()` (full YAML 1.2 unescaping).                |
| `YamlMappingPatchHelper`    | High-level patch helpers: `replacementPatch()` (replace a value), `deletionPatch()` (delete full key–value line), `insertionPatch()` (insert after a pair with auto-indent). |
| `YamlTreeSitterCoreFactory` | Creates the `YamlTreeSitterCore` FFI binding (call once per process, share as singleton).                                                                                    |

### Domain model

| Class                | Description                                                                                                                                                                                                                                                               |
|----------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `YamlDocument`       | Fully immutable value object: `source`, `tree`, `index`, `lineMap`; `isEmpty()` returns `true` for empty or comment-only documents.                                                                                                                                       |
| `YamlIndex`          | Maps segment paths to `YamlMappingPairRef` entries (mappings only); primary API uses `list<string>` segments (`get()`, `find()`, `has()`, `childrenOf()`, `allPaths()`); dot-string convenience variants (`getByPath()`, `hasByPath()`, etc.) available for simple cases. |
| `YamlMappingPairRef` | A reference to a key–value pair: `segments` (`list<string>`), `keyText`, spans (`keySpan()`, `valueSpan()`, `pairSpan()`), text helpers (`keyText()`, `valueText()`, `pairText()`); `path()` returns the dot-joined string as a convenience.                              |
| `YamlPatch`          | A single text replacement: `YamlSpan` + replacement string.                                                                                                                                                                                                               |
| `YamlSpan`           | Byte range `[startByte, endByte)`.                                                                                                                                                                                                                                        |
| `YamlLineMap`        | Binary-search index for byte-offset → `YamlLocation` conversion.                                                                                                                                                                                                          |
| `YamlLocation`       | Human-readable location: 1-based `line` and byte `col`.                                                                                                                                                                                                                   |
| `YamlCstTree`        | Parsed CST tree; use `$document->tree` to access sequences and nodes.                                                                                                                                                                                                     |
| `YamlCstNodeRef`     | Reference to a single CST node; `type()`, `nextNamedSibling()`, `previousNamedSibling()`.                                                                                                                                                                                 |
| `YamlNodeType`       | Enum of known YAML node types (`BLOCK_MAPPING_PAIR`, `FLOW_PAIR`, `ERROR`, …).                                                                                                                                                                                            |
| `YamlNodeField`      | Enum of tree-sitter field names (`KEY`, `VALUE`) for `childByField()`.                                                                                                                                                                                                    |
| `YamlTreeSitterCore` | Low-level FFI wrapper around the tree-sitter C library; pass to `parse()` / `apply()`.                                                                                                                                                                                    |

### Exceptions

All library exceptions implement `YamlCstExceptionInterface`, so a single `catch` is enough:

```php
use Mougrim\YamlCst\Exception\YamlCstExceptionInterface;

try {
    $document = $parser->parse($yaml, $core);
    $pair = $document->index->get(['database', 'host']);
} catch (YamlCstExceptionInterface $e) {
    // handles all yaml-cst exceptions
}
```

| Exception                          | Thrown when                                                                        |
|------------------------------------|------------------------------------------------------------------------------------|
| `YamlSyntaxException`              | The YAML source contains syntax errors.                                            |
| `PathNotFoundException`            | `YamlIndex::get()` or `YamlIndex::getByPath()` is called with a non-existent path. |
| `PatchConflictException`           | Two patches overlap; exposes `$previousSpan` and `$currentSpan`.                   |
| `MaxNestingDepthExceededException` | YAML document nesting depth exceeds the built-in limit (512 levels).               |
| `AbiMismatchException`             | `libtree-sitter` and `tree-sitter-yaml` have incompatible ABI versions.            |
| `YamlTreeSitterException`          | Native library not found, FFI disabled, or a tree-sitter C function returned NULL. |
| `YamlCstExceptionInterface`        | Marker interface implemented by all of the above.                                  |

## License

This project is licensed under the [MIT License](LICENSE).
