# Complex Data Handling in Parallite

## Overview

Parallite uses **MessagePack** for serialization, which is efficient but has some limitations with complex PHP data
structures. This guide explains how to handle complex data safely.

## ✅ No Data Loss!

**Good news!** With the improved Go daemon (v2.0+), the automatic normalization **preserves your data structure**:

- ✅ **Non-sequential integer keys preserved**: `[1 => 'Alice', 5 => 'Bob', 10 => 'Charlie']`
- ✅ **Mixed integer/string keys preserved**: `[0 => 'first', 'name' => 'test', 1 => 'second']`
- ✅ **All array structures work as expected**

### What Gets Normalized

The DataNormalizerService only converts:

1. **Objects with `toArray()`** → Arrays (e.g., Eloquent models)
2. **stdClass objects** → Arrays
3. **DateTime objects** → Structured arrays with timezone info
4. **NaN/Infinity floats** → `null`
5. **Resources** → Error (cannot be serialized)

**Your array keys are safe!**

## The Challenge

MessagePack serialization can fail with:

- **Mixed key types** (integer + string keys in same array)
- **Non-sequential integer keys** (sparse arrays)
- **Eloquent models** (contain database connections and circular references)
- **Resources** (file handles, database connections)
- **Very large nested structures**

## Solutions Implemented

### Automatic Normalization

The worker now **automatically normalizes** all return values before serialization:

```php
// Just return your data - normalization happens automatically
$result = await(async(function () {
    return [
        'users' => User::query()->limit(100)->get(), // Eloquent collection
        'stats' => ['total' => 100, 'active' => 75],
    ];
}));
```

## Best Practices

### ✅ Arrays Work Naturally

You can now use any array structure without worrying:

```php
// All of these work perfectly!

// Sequential arrays
return [0 => 'a', 1 => 'b', 2 => 'c'];

// Non-sequential integer keys
return [1 => 'Alice', 5 => 'Bob', 10 => 'Charlie'];

// Mixed integer/string keys
return [0 => 'first', 'name' => 'test', 1 => 'second'];

// Associative arrays
return ['name' => 'Alice', 'age' => 30];
```

### ✅ DO: Convert Objects to Arrays

```php
// Good - convert Eloquent models to arrays
return [
    'users' => User::query()->get()->map->toArray(),
];
```

### ❌ DON'T: Return Raw Eloquent Collections

```php
// Bad - Eloquent collections contain non-serializable data
return User::query()->get(); // May cause issues
```

### ✅ DO: Limit Data Size

```php
// Good - limit query results
return [
    'top_products' => Product::query()
        ->orderBy('sales', 'desc')
        ->limit(1000)
        ->get()
        ->toArray(),
];
```

### ❌ DON'T: Return Huge Datasets

```php
// Bad - may exceed payload limits
return Product::query()->get(); // Could be millions of records
```

## How DataNormalizer Works

The `DataNormalizerService` class:

1. **Detects problematic structures**
    - Mixed key types
    - Non-sequential integer keys
    - Objects with `toArray()` method

2. **Converts to MessagePack-safe format**
    - Converts mixed keys to all strings
    - Calls `toArray()` on objects
    - Handles DateTime objects specially

3. **Prevents infinite recursion**
    - Max depth limit (default: 100 levels)
    - Throws exception if exceeded

## Configuration

### Increase Payload Size Limit

In `parallite.json`:

```json
{
  "go_overrides": {
    "max_payload_bytes": 52428800
  }
}
```

- Default: 10MB (10485760 bytes)
- Max recommended: 50MB (52428800 bytes)

### Enable Debug Logs

```json
{
  "worker_debug_logs": true,
  "debug_logs": true
}
```

This helps diagnose serialization issues.

## Troubleshooting

### Error: "msgpack: invalid code"

**Cause**: Data structure incompatible with MessagePack

**Solution**:

1. Use `normalize_data()` explicitly
2. Check for mixed key types
3. Ensure no resources in return value

### Error: "Maximum recursion depth exceeded"

**Cause**: Circular references or very deep nesting

**Solution**:

1. Flatten your data structure
2. Increase max depth: `normalize_data($data, 20)`
3. Break circular references

### Error: "Failed to pack response"

**Cause**: Payload too large

**Solution**:

1. Use `truncate_array()` to limit size
2. Increase `max_payload_bytes` in config
3. Return only essential data

## Examples

See `/examples/complex-data.php` for complete working examples:

```bash
php examples/complex-data.php
```

## Architecture Note

**Why MessagePack?**

Parallite uses MessagePack because:

- The Go daemon needs a binary format for efficiency
- MessagePack is supported in both PHP and Go
- It's faster and more compact than JSON
- Go's `github.com/vmihailenco/msgpack/v5` library is mature

**Can we use JSON instead?**

Not easily - the Go daemon would need to be modified to support multiple serialization formats. MessagePack is the best
balance of performance and compatibility.

## Summary

- ✅ Worker automatically normalizes return values
- ✅ Use `normalize_data()` for manual control
- ✅ Use `truncate_array()` for large datasets
- ✅ Convert objects to arrays
- ✅ Use sequential arrays when possible
- ✅ Limit data size in queries
- ❌ Avoid mixed key types
- ❌ Avoid returning raw Eloquent collections
- ❌ Avoid huge datasets without truncation
