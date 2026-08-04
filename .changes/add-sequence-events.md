---
bump: minor
type: Added
---

Four Laravel events for hooking into sequence lifecycle without touching the
package's internals: `SequenceGenerated` (fired on every generated number,
including recycled ones), `SequenceExhausted` (an early warning when a
sequence crosses a configurable percentage of its `max_value`, fired once
per partition until reset), `SequenceRecycled` (a number is actually
inserted into the recycle pool), and `SequenceResetPerformed` (`Sequence::reset()`
runs, whether called directly or via `sequence:reset`). The exhaustion
threshold defaults to 90% of `max_value` (new `exhaustion_threshold` config
key), overridable per-sequence via `getSequenceConfig()`.
