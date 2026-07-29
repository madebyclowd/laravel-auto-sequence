# Contributing

Thanks for considering a contribution to Laravel Auto Sequence.

## Development setup

```bash
git clone https://github.com/madebyclowd/laravel-auto-sequence.git
cd laravel-auto-sequence
composer install
```

## Running checks locally

```bash
vendor/bin/pint --test        # code style
vendor/bin/phpstan analyse    # static analysis
vendor/bin/phpunit            # test suite
```

All three run in CI on every push and pull request; a PR won't be merged unless they pass.

## Pull requests

- Target the `main` branch.
- Add or update tests for any behavior change.
- Run `vendor/bin/pint` (without `--test`) to auto-fix style before committing.
- Keep PRs focused — one logical change per PR.
- Use [Conventional Commits](https://www.conventionalcommits.org/) for commit messages (`feat:`, `fix:`, `chore:`, etc.) — `CHANGELOG.md` and version bumps are generated automatically from these by release-please, don't edit `CHANGELOG.md` by hand.

## Reporting bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.md). Include Laravel/PHP versions and a minimal reproduction.

## Reporting security vulnerabilities

Do not open a public issue — see [SECURITY.md](SECURITY.md).
