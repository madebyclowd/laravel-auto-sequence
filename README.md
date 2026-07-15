# Laravel AutoSequence

[![Latest Version on Packagist](https://img.shields.io/packagist/v/madebyclowd/laravel-auto-sequence.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-auto-sequence)
[![Total Downloads](https://img.shields.io/packagist/dt/madebyclowd/laravel-auto-sequence.svg?style=flat-square)](https://packagist.org/packages/madebyclowd/laravel-auto-sequence)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

Automatically generate custom, sequential numbers (like `INV-2026-0001`, `ORD-999`, etc.) for your Laravel models.

It is completely automatic and guarantees **zero duplicate numbers** and **no accidental gaps**, even when thousands of users are placing orders or creating invoices at the exact same millisecond.

---

## Why not just use the normal auto-increment `id`?

Using a model's default database `id` (1, 2, 3...) is fine for relationships, but a bad idea to show to your users because:
1. **It leaks data**: Your customers will know exactly how many invoices or orders you have processed.
2. **It is unpredictable**: It might start at 1 in your local testing database but start at 14,000 on production.
3. **It is boring**: You can't customize it to include the current year, a department code, or dynamic prefixes (e.g., you can't easily turn `id = 5` into `INV-2026-NY-0005`).

This package solves this by generating a separate, beautifully formatted number column for you (like `invoice_number`), while ensuring two simultaneous database queries never try to claim the exact same number.

---

## Features

*   **Concurrency Safety**: Utilizes pessimistic database locking (`SELECT ... FOR UPDATE`) or Redis-based distributed locks to prevent duplicate number generation.
*   **Hi/Lo Pre-Allocation Caching**: Optionally allocates sequence numbers in blocks (e.g., 50 at a time) and increments them in-memory to reduce database lock contention.
*   **Composite Key Partitioning**: Segregates counters using composite primary keys `['module', 'type_code', 'period', 'scope']`.
*   **Period Resets**: Automatically resets counters on date boundaries (daily, weekly, monthly, yearly) or custom fiscal periods.
*   **Dynamic Rules & Scopes**: Resolves type prefixes from model relations (e.g. `$invoice->branch->code`) and scopes sequences by organizational units (e.g. `$invoice->tenant_id`).
*   **Flexible Format Placeholders**: Supports template placeholders like date tokens (`{YYYY}`, `{MM}`, `{date:d-M-Y}`), dynamic model attributes (`{attribute:customer_code}`), and random strings (`{rand:8}`).
*   **Verification & Repair Commands**: Includes Artisan commands to audit model records, detect counter drift, and repair sequence state.

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13 (`illuminate/support` and `illuminate/database`)

---

## Table of Contents

- [Features](#features)
- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
- [Format Template Tokens](#format-template-tokens)
- [Advanced Usage](#advanced-usage)
- [Manual Generation (Facade)](#manual-generation-facade)
- [Artisan Commands](#artisan-commands)
- [Configuration](#configuration-configauto-sequencephp)
- [Troubleshooting / FAQ](#troubleshooting--faq)
- [License](#license)

---

## Quick Start

**1. Install the package:**

```bash
composer require madebyclowd/laravel-auto-sequence
```

**2. Run the setup wizard** — it asks a couple of yes/no questions and creates the database tables
the package needs (you can just press Enter to accept the sensible defaults):

```bash
php artisan sequence:install
```

**3. Add a `number` column to the model you want to sequence** (a plain `string` column, nullable
or with a default of `null`), then wire up the model:

```php
use MadeByClowd\AutoSequence\Contracts\AutoSequence;
use MadeByClowd\AutoSequence\Traits\HasSequenceNumber;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model implements AutoSequence
{
    use HasSequenceNumber;

    public function getSequenceConfig(): array
    {
        return [
            'number' => [
                'module' => 'invoice',       // groups this sequence's counter
                'type_code' => 'INV',        // the prefix in the generated number
                'period' => 'monthly',       // counter resets every month
                'format_template' => '{type_code}-{YYYY}-{MM}-{seq:5}',
            ],
        ];
    }
}
```

**4. Create a record like normal — the column fills itself in:**

```php
$invoice = Invoice::create([...]); // no need to set 'number' yourself
echo $invoice->number; // "INV-2026-06-00001"
```

That's it — every new `Invoice` now gets a safe, formatted, auto-incrementing number. Read on for
the full set of options.

---

## Basic Usage

### 1. Implement and Configure Your Model

Add the `AutoSequence` contract and use the `HasSequenceNumber` trait on your Eloquent model, then
return one array entry per column you want to auto-fill from `getSequenceConfig()`:

```php
use MadeByClowd\AutoSequence\Contracts\AutoSequence;
use MadeByClowd\AutoSequence\Traits\HasSequenceNumber;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model implements AutoSequence
{
    use HasSequenceNumber;

    /**
     * Return the sequence configurations for the model.
     */
    public function getSequenceConfig(): array
    {
        return [
            'number' => [
                'module' => 'invoice',
                'type_code' => 'INV',
                'period' => 'monthly', // daily, weekly, monthly, yearly, never (or custom date formats)
                'format_template' => '{type_code}-{YYYY}-{MM}-{seq:5}', // Outputs: INV-2026-06-00001
                'pad_length' => 5,
            ]
        ];
    }
}
```

Only `module` and `format_template` are required — everything else falls back to a sensible
default (see [Configuration](#configuration-configauto-sequencephp) and
[Additional Configuration Options](#7-additional-configuration-options)).

### 2. Manual Override Protection
If you manually assign a value to the sequenced attribute before saving, the package will respect it and skip generation:

```php
$invoice = new Invoice();
$invoice->number = 'MANUAL-999';
$invoice->save(); // Bypasses sequence generator, keeping 'MANUAL-999'
```

---

## Format Template Tokens

`format_template` is a plain string with placeholders. Mix and match any of these:

| Token | Produces | Example |
|---|---|---|
| `{type_code}` | The resolved type code/prefix | `INV` |
| `{seq:N}` | The incrementing counter, zero-padded to `N` digits | `{seq:5}` → `00042` |
| `{YYYY}` / `{MM}` / `{DD}` | Current year / month / day | `2026`, `06`, `16` |
| `{date:FORMAT}` | Any [PHP date format](https://www.php.net/manual/en/datetime.format.php) | `{date:d-M-Y}` → `16-Jun-2026` |
| `{period}` | The raw value returned by a custom `period` callable | `FY2026` |
| `{attribute:column}` | A live attribute from the model being saved (dot-notation for relations) | `{attribute:branch.company.code}` |
| `{rand:N}` | A random alphanumeric string of length `N` | `{rand:8}` → `aZ3kQ9pL` |

Example combining several: `'{type_code}-{YYYY}-{attribute:branch.code}-{seq:5}'`.

---

## Advanced Usage

### 1. Dynamic Type Code Resolution (`type_relation`)
Resolve the type code prefix from a model relationship dynamically (e.g., Bali branch `DPS` vs Jakarta branch `JKT`):

```php
'number' => [
    'module' => 'invoice',
    'type_relation' => 'branch', // Calls $model->branch->code
    'default_type' => 'HQ',       // Fallback if relation is missing
    'period' => 'yearly',
    'format_template' => '{type_code}-{YYYY}-{seq:5}',
]
```

To use a relationship column other than `code`:
```php
'type_relation' => [
    'relation' => 'category',
    'column' => 'id_code',
]
```

### 2. Multi-Tenant Scoping (`scope`)
Isolate sequence pools across tenants or branches by specifying a scoping model attribute:

```php
'number' => [
    'module' => 'invoice',
    'type_code' => 'INV',
    'scope' => 'tenant_id', // Evaluates $model->tenant_id dynamically to separate counters
    'format_template' => '{type_code}-{YYYY}-{seq:5}',
]
```

### 3. Custom Reset Callables (Fiscal Calendar)
Provide a closure or a custom class string to partition sequences by custom dates (e.g., fiscal years starting in April):

```php
'number' => [
    'module' => 'invoice',
    'type_code' => 'INV',
    'period' => function ($model) {
        $createdAt = $model->created_at ?? now();
        $year = $createdAt->month >= 4 ? $createdAt->year : $createdAt->year - 1;
        return "FY{$year}";
    },
    'format_template' => '{type_code}-{period}-{seq:5}',
]
```

### 4. Custom PHP Date Formatting (`{date:FORMAT}`)
Format the template using standard PHP date character parameters:

```php
'format_template' => '{type_code}-{date:d-M-Y}-{seq:5}' // INV-16-Jun-2026-00001
```

### 5. Multi-Column Sequences
Generate sequences for multiple attributes on a single model:

```php
public function getSequenceConfig(): array
{
    return [
        'invoice_number' => [
            'module' => 'invoice',
            'type_code' => 'INV',
            'format_template' => '{type_code}-{YYYY}-{seq:5}',
        ],
        'internal_ref' => [
            'module' => 'internal',
            'type_code' => 'REF',
            'period' => 'never',
            'format_template' => '{type_code}-{seq:8}',
        ]
    ];
}
```

### 6. Closure Format Templates & Nested Relation Placeholders
Customize your formatting templates dynamically using closures or fetch nested relation attributes using dot-notation:

```php
'number' => [
    'module' => 'invoice',
    'type_code' => 'INV',
    // 1. Fetching a nested relationship attribute:
    'format_template' => 'INV-{attribute:branch.company.code}-{seq:5}', 
    
    // 2. Closure-based template:
    'format_template' => function ($model) {
        return 'INV-' . ($model->is_priority ? 'URGENT' : 'NORMAL') . '-{seq:5}';
    }
]
```

### 7. Additional Configuration Options
The following additional configuration options are supported within the model sequence settings:

```php
'number' => [
    'module' => 'invoice',
    'type_code' => 'INV',
    'format_template' => '{type_code}-{seq:5}',
    
    // Set a custom starting value (defaults to 1)
    'start_value' => 1000, 
    
    // Set a custom increment step size (defaults to 1)
    'step' => 2, 
    
    // Set a maximum limit. Throws a SequenceExhaustedException if exceeded
    'max_value' => 99999, 
    
    // Enforce sequence integrity. Throws a AutoSequenceException if a manual value is set before saving
    'allow_manual' => false, 
    
    // Enable D365 continuous sequence (recycles deleted numbers automatically)
    'continuous' => true,
    
    // Database connection override for this specific sequence
    'connection' => 'tenant_db_connection', 
]
```

### 8. Soft Deletes & Concurrency Best Practices

To ensure data integrity and prevent service disruption in high-volume production systems, the package implements the following behaviors:

*   **Soft Deletes Protection**: If your Eloquent model uses Laravel's `SoftDeletes` trait, deleting a record (soft delete) will **not** trigger sequence number recycling. The sequence number remains reserved on the soft-deleted record in the database. This allows restoring the model via `$model->restore()` without causing duplicate sequence collisions. The sequence number is only recycled when the model is permanently deleted via `$model->forceDelete()`.
*   **Preventing PHP-FPM Thread Exhaustion**: Under the `database` locking driver, the package automatically sets session-level or local transaction-level lock wait timeouts on the database connection (using MySQL's `innodb_lock_wait_timeout`, Postgres's `lock_timeout`, SQL Server's `LOCK_TIMEOUT`, or SQLite's `busy_timeout`). If a transaction holds a sequence lock for too long, subsequent requests fail fast with a `SequenceLockException` after the configured timeout (default 5s) instead of blocking indefinitely, preventing worker exhaustion and gateway 502/504 errors.
*   **Pre-Allocation & Transaction Modes**: High-performance pre-allocation (`pre_allocation.enabled => true`) cannot be used together with the `'gapless'` transaction mode. In `'gapless'` mode, a rolled-back transaction would roll back the database counter but keep the pre-allocated block in memory/cache, resulting in duplicate sequence collisions. To use pre-allocation, set `transaction_mode => 'gap_tolerant'`.

---

## Manual Generation (Facade)

Inject sequence values programmatically (e.g. in custom jobs, observers, or seeds):

```php
use MadeByClowd\AutoSequence\Facades\Sequence;

// Fetch and increment next sequence value (with optional connection, start value, step, continuous, max value)
$number = Sequence::generate(
    'order', 
    'SO', 
    '202606', 
    '{type_code}-{YYYY}-{seq:5}', 
    5, 
    'tenant_1',
    null,       // $model (optional)
    null,       // $connection (optional override)
    1,          // $startValue (optional, default 1)
    1,          // $step (optional, default 1)
    false,      // $continuous (optional, default false)
    99999       // $maxValue (optional, default null)
);

// Recycle a sequence number manually (inserts it back into sequence_recycled table)
Sequence::recycle('order', 'SO', '202606', 'tenant_1', 105);

// Get current value without incrementing
$current = Sequence::getCurrent('order', 'SO', '202606', 'tenant_1');

// Reset or offset the counter
Sequence::reset('order', 'SO', '202606', 'tenant_1', 100);
```

---

## Artisan Commands

### List Sequences
Display a table of all active sequence counters in the database:
```bash
php artisan sequence:list
php artisan sequence:list --module=invoice
```

### Reset Counters
Reset or set a specific sequence counter manually:
```bash
php artisan sequence:reset invoice INV --value=100
```

### Verify and Repair
Scan actual model tables for sequence column values, identify any counter drift, and optionally align the database sequence counters to prevent key collisions:
```bash
php artisan sequence:verify "App\Models\Invoice" number --type=INV --module=invoice
# To automatically repair:
php artisan sequence:verify "App\Models\Invoice" number --type=INV --module=invoice --repair
```

---

## Configuration (`config/auto-sequence.php`)

Publishing configuration gives you full architectural control:

```php
return [
    'table' => 'sequences',
    'recycled_table' => 'sequence_recycled',
    'connection' => null,

    // Concurrency Locking Strategy
    'locking' => [
        'driver' => 'database',   // 'database' (Pessimistic lock), 'cache' (Atomic lock), or 'none'
        'cache_store' => null,    // cache connection name for atomic locks
        'timeout' => 5,           // seconds to block waiting for a lock (applies native DB lock timeout for MySQL, Postgres, SQL Server, SQLite)
        'retry_interval' => 100,  // milliseconds between retry attempts (applies if locking driver is cache)
    ],

    // Transaction Mode:
    // 'gapless': increments within model transaction (rolls back on failure; no gaps)
    // 'gap_tolerant': increments in isolated transaction (commits immediately; minimizes lock duration)
    // Note: 'gapless' is incompatible with pre-allocation.
    'transaction_mode' => 'gapless',

    // Hi/Lo Pre-Allocation Caching
    'pre_allocation' => [
        'enabled' => false,
        'block_size' => 50, // Grab 50 numbers at a time
        'store' => null,    // dedicated cache store name (e.g. 'redis') to prevent gaps from LRU eviction/flushes
    ],

    // Audit Tracking
    'audit' => [
        'enabled' => false, // Toggle created_by / updated_by tracking columns
        'user_model' => 'App\Models\User',
        'created_by_column' => 'created_by',
        'updated_by_column' => 'updated_by',
        'user_id_type' => 'bigInteger', // Options: 'bigInteger', 'uuid', 'ulid', 'string'
    ],
];
```

You don't have to publish this file at all — every key above has a working default, so skip it
unless you actually need to change something.

---

## Troubleshooting / FAQ

**My column stays `null` after `Model::create()`.**
Make sure the model `implements AutoSequence` (not just uses the trait) and that you didn't
manually set the column to a non-empty value before saving — see
[Manual Override Protection](#2-manual-override-protection).

**I get a lock/timeout error under load.**
You're likely on the `'cache'` locking driver with a store that doesn't support atomic locks.
`file` and `array` cache drivers don't support concurrent locks properly — switch to `redis`,
`memcached`, or the `'database'` driver (see `locking.driver` in
[Configuration](#configuration-configauto-sequencephp)).

**I enabled `pre_allocation` and got a configuration exception.**
Pre-allocation requires `transaction_mode => 'gap_tolerant'` — it's incompatible with the default
`'gapless'` mode. See [Pre-Allocation & Transaction Modes](#8-soft-deletes--concurrency-best-practices).

**A restored (soft-deleted) record didn't get its number recycled — is that a bug?**
No — that's intentional. Numbers are only recycled on `forceDelete()`, never on a soft delete, so a
later `restore()` can't collide with a number already handed out in the meantime.

**How do I fix a counter that's out of sync with my actual data?**
Run `php artisan sequence:verify {model} {column} --repair` — see
[Verify and Repair](#verify-and-repair).

**Can I use this with UUID/ULID primary keys?**
Yes — the sequence counter lives in its own table (`sequences`), keyed by
`module`/`type_code`/`period`/`scope`, completely independent of your model's primary key type.

---

## License

The MIT License (MIT). Please see the [LICENSE](LICENSE) file for more information.
