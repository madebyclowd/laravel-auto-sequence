# Changelog

All notable changes to `laravel-auto-sequence` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Static analysis (Larastan) in CI, enforced on every push/PR.
- Test coverage reporting via Codecov.
- Dependency vulnerability scanning (`composer audit`) and Dependabot updates for Composer + GitHub Actions.
- `SECURITY.md` vulnerability disclosure policy and GitHub private vulnerability reporting.
- `CONTRIBUTING.md`, issue templates, and PR template.
- `.gitattributes` to exclude dev-only files from distributed package archives.

## [1.0.3] - 2026-07-15
### Changed
- Refactored and clarified package description and README documentation.

## [1.0.2] - 2026-07-07
### Changed
- Overhauled README with quick start and troubleshooting sections.
- Added Laravel Boost integration and simplified installation instructions.
- Simplified package description and refined feature documentation.

## [1.0.1] - 2026-06-16
### Changed
- Renamed package from Laravel Sequenceable to Laravel Auto Sequence, including namespaces, console commands, and error messages.

## [1.0.0] - 2026-06-16
### Added
- Initial release: sequential invoice/order number generation for Laravel Eloquent models, safe under concurrent writes via pessimistic/Redis locking.

[Unreleased]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.3...HEAD
[1.0.3]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/madebyclowd/laravel-auto-sequence/releases/tag/v1.0.0
