# Contributing

Thank you for considering a contribution to `mougrim/yaml-cst`!

## Requirements

- Docker (recommended — handles all native dependencies automatically)

## Getting started

```bash
# Clone the repository
git clone https://github.com/mougrim/php-yaml-cst.git
cd php-yaml-cst

# Build the Docker image (compiles tree-sitter native libraries)
make build

# Install PHP dependencies
make install

# Run tests
make test

# Run static analysis
make phpstan
```

## Workflow

1. Fork the repository and create a feature branch off `main`.
   Branch names should follow the pattern `<type>/<short-description>`, where `<type>` is one of:
   - `feature/` — new functionality
   - `fix/` — bug fixes
   - `refactor/` — code restructuring without behaviour change
   - `docs/` — documentation-only changes

   Examples: `feature/yaml-document-is-empty`, `fix/patch-conflict-offset`.

2. Write code and tests. Every public API change must be covered by tests.
3. Ensure all checks pass locally:
   ```bash
   make ci
   ```
   `make ci` runs the full pipeline in order: `build` → `install` → `test` → `phpstan` → `cs-check`.
4. Update `CHANGELOG.md` under `[Unreleased]` with a brief description of your change.
5. Open a pull request against `main`. A good PR description includes:
   - **What** changed (a brief summary).
   - **Why** the change is needed (motivation or linked issue).
   - **How** to test it (test scenarios or reference to new/updated tests).
   - Any **breaking changes** and migration guidance if applicable.

## Code structure

### Namespace conventions

| Namespace                 | Purpose                                                                                                                                                                    |
|---------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Mougrim\YamlCst\` (root) | Top-level service classes (`YamlCstParser`, `YamlCstSearcher`, …)                                                                                                          |
| `DomainModel\`            | Immutable value objects exposed to library users (`YamlDocument`, `YamlSpan`, …)                                                                                           |
| `Dto\`                    | Opaque, internal handles that wrap raw FFI `CData` values (`YamlTreeSitterNodeHandle`, `YamlTreeSitterTreeHandle`). Never expose these to user code. Don't have any logic. |
| `Enum\`                   | String-backed enums that mirror tree-sitter constants (`YamlNodeType`, `YamlNodeField`)                                                                                    |
| `Exception\`              | Public exception hierarchy (all implement `YamlCstExceptionInterface`)                                                                                                     |
| `Factory\`                | Object-creation classes (called once, or from DI wiring)                                                                                                                   |
| `Helper\`                 | Stateless utility classes with no external dependencies                                                                                                                    |

### @internal annotation

Mark a class or method `@internal` when it is an implementation detail that must remain public
(e.g. for cross-package access or test construction) but is **not part of the stable public API**.
Internal classes may change in any release without a semver major bump.

Examples of correctly annotated internals:
- `YamlIndexBuilder` — used by `YamlCstParser` but not intended for direct use
- `YamlIndexNode` — mutable trie node, only written during the build phase
- `YamlLineMapFactory`, `YamlSyntaxExceptionFactory` — construction helpers

Do **not** mark classes `@internal` if they appear in public method signatures or must be
extended/implemented by users.

## Code style

- All PHP files must declare `strict_types=1`.
- Follow PSR-1 naming conventions.
- Use `readonly` classes/properties where state is immutable.
- PHPStan must pass at `level: max` — no new `ignoreErrors` without justification.
- Every public API change must be covered by tests. Coverage expectation: at least one happy-path test and the key edge cases (e.g. empty input, boundary values, error paths). A single test is not sufficient when the behaviour has observable edge cases.

Code style is enforced by [php-cs-fixer](https://cs.symfony.com). Before opening a pull request, check
and fix any style violations locally:

```bash
make cs-check   # check only (shows a diff)
make cs-fix     # apply fixes in place
```

The `make ci` target also runs `cs-check` as part of the full pipeline.

## Tests

- **Unit tests** (`tests/Unit/`) — pure PHP, no FFI required.
- **Integration tests** (`tests/Integration/`) — require tree-sitter `.so` files to be installed on the system.

Run them separately with:

```bash
make test-unit
make test-integration
```

### Test conventions

#### Assertion style

Prefer a single `self::assertSame()` with two mirrored arrays over multiple separate assertions:

```php
// Good — one assertion, both expected and actual are equally readable
self::assertSame(
    ['line' => 1, 'col' => 1],
    ['line' => $location->line, 'col' => $location->col],
);

// Avoid — multiple assertions make it harder to see what's being compared
self::assertSame(1, $location->line);
self::assertSame(1, $location->col);
```

#### Fixture builders

Use the builders in `tests/FixtureBuilder/` to construct test objects with sensible defaults:

```php
$span = new YamlSpanFixtureBuilder()->build(startByte: 5, endByte: 10);
$patch = new YamlPatchFixtureBuilder()->build(span: $span, replacement: 'new');
```

#### CoversClass attribute

Every test class must have one or more `#[CoversClass(...)]` attributes corresponding to the class(es) it directly tests. This enables strict coverage reporting.

#### Data providers

Data provider methods must be placed immediately before the test method that uses them (enforced by php-cs-fixer). Name them with the suffix `Provider` (e.g. `locateProvider()` for `testLocate()`).

#### YAML strings in tests

YAML strings in tests must be written as heredocs to preserve readability and indentation:

```php
$yaml = <<<'YAML'
    key: value
    nested:
      child: data
    YAML;
```

## Reporting issues

Please use [GitHub Issues](https://github.com/mougrim/php-yaml-cst/issues) and include:

- PHP version (`php -v`)
- `libtree-sitter` version
- `tree-sitter-yaml` version
- A minimal reproducible example (YAML snippet + expected vs actual behavior)
