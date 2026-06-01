# Complex Data Handling in Parallite

## Overview

Parallite uses `pcntl_fork` — forked child processes **inherit the parent's entire memory space**. This means closures have direct access to all variables from the parent scope without serialization. Results are written to a temp file as JSON.

## What Changed (vs Daemon Architecture)

With the previous daemon + MessagePack architecture, complex data handling required careful normalization. With `pcntl_fork`:

- **No closure serialization** — closures run directly in the forked process
- **No MessagePack constraints** — results are JSON-encoded
- **No payload size limits** — only limited by temp file disk space and `memory_limit`

## Best Practices

### ✅ Closures Inherit Parent Scope

Forked processes copy the parent's memory, so `use` variables work naturally:

```php
$config = ['db' => 'postgres', 'timeout' => 30];

$result = await(async(function () use ($config) {
    // $config is available — no serialization needed
    return $config['db'];
}));
```

### ✅ Return JSON-Serializable Values

Child processes write results as JSON via `json_encode()`. Return values must be JSON-serializable:

```php
// Good — arrays, scalars, nested structures
return ['name' => 'Alice', 'scores' => [95, 87, 91]];

// Good — sequential arrays
return ['a', 'b', 'c'];
```

### ❌ Don't Return Non-JSON-Serializable Values

```php
// Bad — resources cannot be JSON-encoded
return fopen('/tmp/file', 'r');

// Bad — objects without JsonSerializable
return new SomeObject();
```

### ✅ Convert Objects to Arrays

```php
// Good — convert Eloquent models to arrays
return User::query()->limit(100)->get()->toArray();

// Bad — raw Eloquent collections may not serialize cleanly
return User::query()->get();
```

### ✅ Limit Data Size

Large results write to temp files and are read back by the parent. Keep results reasonable:

```php
// Good — limit query results
return Product::query()
    ->orderBy('sales', 'desc')
    ->limit(1000)
    ->get()
    ->toArray();
```

## Key Differences from Daemon Mode

| Aspect | Daemon (old) | Fork (current) |
| --- | --- | --- |
| Closure transfer | Serialized via opis/closure | Inherited via fork — no serialization |
| Result transport | MessagePack over socket | JSON in temp file |
| Data size limit | ~10MB (configurable) | Limited by disk/memory only |
| Key types | Required normalization | JSON handles all key types natively |
| Objects | Must implement `toArray()` | Must be JSON-serializable |

## Summary

- ✅ Closures inherit parent scope — no `opis/closure` needed
- ✅ Return JSON-serializable values (arrays, scalars, nested structures)
- ✅ Convert Eloquent models with `->toArray()`
- ✅ Limit large query results
- ❌ Don't return resources or non-serializable objects
- ❌ Don't capture `$this` in closures (causes the entire object to be copied into the fork)
