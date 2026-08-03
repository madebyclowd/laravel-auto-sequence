---
bump: minor
type: Added
---

Releases are now automated: contributors add a small changeset file under
`.changes/` describing their change, and a GitHub Actions workflow
aggregates pending changesets into a `CHANGELOG.md` update and opens a
Version PR. Merging that PR tags and releases automatically — no more
manual `git tag` push. See [.changes/README.md](.changes/README.md).
