---
bump: minor
type: Added
---

Two new format template tokens: a `quarterly` period option
(`resolveSequencePeriod()` now supports `'period' => 'quarterly'`,
rendering `YYYY'Q'Q` e.g. `2026Q3`), and `{checksum:mod10}`, a Luhn
check digit computed over every digit rendered before it in the
template. Validate a generated number with the new
`Sequence::isValidChecksum()`.
