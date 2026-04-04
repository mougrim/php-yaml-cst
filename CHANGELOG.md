# Changelog

All notable changes to `mougrim/yaml-cst` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## API stability

Classes and methods **not** annotated `@internal` are part of the stable public API and follow
SemVer: breaking changes only in major releases, new features in minor releases, bug fixes in
patch releases.

Classes annotated `@internal` (e.g. `YamlIndexBuilder`, `YamlIndexNode`, `YamlLineMapFactory`,
`YamlSyntaxExceptionFactory`) are implementation details and may change in any release.

## [Unreleased]

### Added
- Initial public release.

[Unreleased]: https://github.com/mougrim/php-yaml-cst/commits/main
