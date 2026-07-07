## laravel-auto-sequence

Concurrency-safe, customizable sequence number generator for Eloquent models (invoices, orders,
CRM records, etc.). Pessimistic DB locking or Redis distributed locks prevent duplicate numbers;
composite key `['module', 'type_code', 'period', 'scope']` partitions independent counters.

### Core conventions

- Implement `MadeByClowd\AutoSequence\Contracts\AutoSequence` and use
  `MadeByClowd\AutoSequence\Traits\HasSequenceNumber` on the model; define `getSequenceConfig()`
  returning one entry per sequenced column (`module`, `type_code`, `period`, `format_template`).
- If the sequenced column already has a non-empty value before save, generation is skipped
  (Manual Override Protection) — don't pre-fill the column unless that's intended.
- `period` accepts `daily|weekly|monthly|yearly|never`, a custom date format, or a callable for
  fiscal-year-style resets. `scope` resolves a model attribute (e.g. `tenant_id`) to isolate pools
  per tenant/branch. `type_relation` resolves a prefix from a related model instead of a static
  `type_code`.
- Manual generation outside the trait: `MadeByClowd\AutoSequence\Facades\Sequence::generate()`,
  `::recycle()`, `::getCurrent()`, `::reset()`.

### Operational commands

- `php artisan sequence:install` — interactive wizard for config/migrations/`migrate`.
- `php artisan sequence:list [--module=]` — active sequence counters.
- `php artisan sequence:reset {module} {type} --value=N` — offset a counter.
- `php artisan sequence:verify {model} {column} [--repair]` — detect/fix drift between the model
  table's max value and the stored counter.

### Pitfalls

- Locking driver `'cache'` requires a lock-capable store (`redis`, `memcached`, `database`) —
  `file`/`array` don't support concurrent locks properly.
- Pre-allocation (Hi/Lo caching) is incompatible with `'gapless'` transaction mode; use
  `'gap_tolerant'` when `pre_allocation.enabled` is true, and give it a dedicated non-evicting
  cache store to avoid silent gaps.
- SoftDeletes: sequence numbers are recycled only on `forceDelete()`, never on a soft delete, to
  avoid collisions if the record is restored.

See the `laravel-auto-sequence` Agent Skill (installed alongside this guideline) for the full
format-template token reference, advanced enterprise config options, and troubleshooting.
