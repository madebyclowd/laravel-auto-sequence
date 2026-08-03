# Changelog

All notable changes to `laravel-auto-sequence` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/).

New entries are generated from `.changes/*.md` changesets — see
[.changes/README.md](.changes/README.md) — not edited here by hand.

## [1.1.1] - 2026-07-29

### Fixed
- Audit trail (`created_by`/`updated_by`) was silently never persisted — `Models\Sequence::$fillable` didn't include the audit columns, so mass-assignment (`new Sequence($attributes)`, `->update()`) dropped them with no error. Now uses `forceFill()`.
- Lock-acquisition timeouts leaked Laravel's internal `Illuminate\Contracts\Cache\LockTimeoutException` instead of the package's own `SequenceLockException` — `Lock::block()` throws rather than returning `false`, so the old `if (! $lock->block(...))` check was dead code. Both the database-lock and pre-allocation lock paths now catch and rethrow correctly.

### Added
- Test coverage for all console commands (`sequence:install`, `sequence:reset`, `sequence:list`, `sequence:verify`), including error/validation branches.
- Test coverage for the audit trail, cache-based locking, and previously-untested template placeholders (`{module}`, `{scope}`, `{rand:X}`, missing-attribute fallback).

### Changed
- Reorganized the test suite from a single 740-line file into focused files under `tests/Feature/Console/` and `tests/Feature/Sequencing/`, with shared fixtures under `tests/Feature/Fixtures/`.

## [1.1.0](https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.3...v1.1.0) (2026-07-29)


### Added

* implement CI static analysis, test coverage reporting, project documentation, and improved repository metadata. ([1438655](https://github.com/madebyclowd/laravel-auto-sequence/commit/1438655c1a92974ce4221f29bf74e3c421755dd9))


### Changed

* add GitHub Actions and Codecov status badges to README ([0269c55](https://github.com/madebyclowd/laravel-auto-sequence/commit/0269c55aca461d53c09cace2b6cad13d514ae052))
* remove broken Codecov badge from README ([1bfe209](https://github.com/madebyclowd/laravel-auto-sequence/commit/1bfe20955ccfaf881fb9b899def9cdf4e3687b0b))

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

[1.1.1]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.1.0...v1.1.1
[1.0.3]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/madebyclowd/laravel-auto-sequence/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/madebyclowd/laravel-auto-sequence/releases/tag/v1.0.0
