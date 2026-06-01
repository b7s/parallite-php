# Parallite PHP — Improvement Plan

## Problem Statement

The Go daemon architecture makes Parallite **slower than sequential execution** for typical PHP workloads:

| Mode | Catraca (8 gates) | assistant/api (8 gates) |
|------|-------------------|-------------------------|
| Sequential | ~2.1s | ~1.9s |
| Parallite (Go daemon) | ~7.9s | ~6.9s |
| pcntl_fork (no daemon) | ~0.9s | ~1.0s |

The daemon adds ~5s startup overhead per invocation. For short-lived CLI tools that run and exit, the persistent-worker model never amortizes its cost. The Go binary, socket IPC, MessagePack protocol, and closure serialization layer form a heavyweight pipeline that costs more than it saves.

## Root Causes

### 1. Daemon startup: ~5s cold cost
- Go binary must be found, validated, and executed (`BinaryResolverService`)
- PHP workers must be spawned (`startWorker()` — 100ms sleep per worker just to "let PHP initialize")
- Socket must be created and `waitForSocket()` polls up to 50 × 100ms = 5s
- Each `ensureDaemonRunning()` call pays this cost on first use

### 2. Multi-layer serialization overhead
- Closure → `opis/closure::serialize()` → MessagePack pack → 4-byte length frame → socket write
- Socket read → 4-byte length frame → MessagePack unpack → `opis/closure::unserialize()` → execute
- Result → `lightNormalize()` → MessagePack pack → 4-byte length frame → socket write
- Socket read → 4-byte length frame → MessagePack unpack → return
- **6 serialization steps per task** (3 in client, 3 in worker)

### 3. External dependencies that add friction
- `opis/closure` — only needed because closures cross process boundaries via serialized strings
- `rybakit/msgpack` — only needed because the Go daemon speaks MessagePack
- Go binary (~10MB) — must be compiled, versioned, cached, and discovered at runtime
- `ext-sockets` — required for Unix domain socket IPC with daemon

### 4. Single-threaded event loop bottleneck
- `EventLoop` processes tasks one at a time on a single goroutine
- `BlockingPool` offloads execution but the event loop still serializes scheduling
- With `fixed_workers: 1` (default), only one task runs at a time — worse than sequential

### 5. IPC overhead per task
- Each `async()` call: socket_create + socket_connect + socket_write
- Each `await()` call: socket_read (with 30s timeout) + socket_close
- Connection setup on Unix sockets is fast but not free — and it happens per-task

## Proposed Solution: Replace Go Daemon with Native PHP pcntl_fork

### Architecture

```
Before (Go daemon):
  PHP Client → MessagePack → Unix Socket → Go Daemon → stdin/stdout → PHP Worker → stdin/stdout → Go Daemon → Unix Socket → PHP Client

After (native pcntl_fork):
  PHP Parent → pcntl_fork() → PHP Child (executes closure directly) → temp file → PHP Parent (pcntl_waitpid + read)
```

**Key insight**: For short-lived parallel tasks in CLI tools, `pcntl_fork()` is faster than any daemon-based approach because:
- Zero startup overhead (no daemon to launch)
- Zero IPC protocol overhead (temp file for results, or pipes)
- Zero serialization of closures (child inherits parent's memory via fork)
- Zero external dependencies (pcntl is a built-in PHP extension)

### Implementation Plan

#### Phase 1: Core ForkExecutor (replace DaemonService + SocketService)

```php
readonly class ForkExecutor
{
    public function __construct(
        private int $maxChildren = 0, // 0 = unlimited
    ) {}

    /**
     * @param Closure(): mixed $closure
     * @return array{pid: int, temp_file: string}
     */
    public function fork(Closure $closure): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'parallite_');
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork() failed');
        }

        if ($pid === 0) {
            // Child process — execute closure, write result, exit
            try {
                $result = $closure();
                $data = ['ok' => true, 'result' => $result];
            } catch (Throwable $e) {
                $data = ['ok' => false, 'error' => $e->getMessage()];
            }
            file_put_contents($tempFile, json_encode($data, JSON_UNESCAPED_SLASHES));
            posix_kill(posix_getpid(), SIGKILL); // Immediate exit, no shutdown handlers
        }

        // Parent — return handle for later collection
        return ['pid' => $pid, 'temp_file' => $tempFile];
    }

    /**
     * @param array<array{pid: int, temp_file: string}> $handles
     * @return array<mixed>
     */
    public function awaitAll(array $handles): array
    {
        $results = [];
        foreach ($handles as $i => $handle) {
            pcntl_waitpid($handle['pid'], $status);
            $raw = file_get_contents($handle['temp_file']);
            $data = json_decode($raw, true);
            @unlink($handle['temp_file']);

            if ($data['ok'] === true) {
                $results[$i] = $data['result'];
            } else {
                throw new RuntimeException($data['error']);
            }
        }
        return $results;
    }
}
```

#### Phase 2: Updated ParalliteClient (backward-compatible API)

The public API (`async()`, `await()`, `awaitAll()`, `Promise`) stays identical. Only the transport layer changes:

```php
class ParalliteClient
{
    private ForkExecutor $executor;

    public function __construct(
        string $socketPath = '',        // kept for BC, ignored in fork mode
        bool $autoManageDaemon = true,  // kept for BC, no-op in fork mode
        ?string $projectRoot = null,
        bool $enableBenchmark = false,
        bool $useFork = true,           // NEW: default to fork mode
    ) {
        if ($useFork && self::pcntlAvailable()) {
            $this->executor = new ForkExecutor();
        } else {
            // Fallback to daemon mode (existing code)
            $this->initDaemonMode($socketPath, $autoManageDaemon, $projectRoot, $enableBenchmark);
        }
    }

    public function async(Closure $closure): array
    {
        if ($this->executor !== null) {
            return $this->executor->fork($closure);
        }
        return $this->socketService->submitTask($closure);
    }

    public function await(array|Promise|null &$future = null): mixed
    {
        if ($this->executor !== null && is_array($future) && isset($future['pid'])) {
            return $this->executor->awaitOne($future);
        }
        // ... existing daemon await logic
    }

    private static function pcntlAvailable(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('posix_getpid');
    }
}
```

#### Phase 3: Remove Go daemon dependency

After fork mode is stable:

1. **Remove** from `composer.json`:
   - `"rybakit/msgpack": "^0.9.1"` — only needed for daemon IPC
   - `"opis/closure": "^4.3"` — only needed to serialize closures across processes (fork inherits memory)
   - Go binary discovery (`BinaryResolverService`, `parallite-install`, `parallite-update`)

2. **Remove** classes:
   - `SocketService` — Unix socket IPC (replaced by fork)
   - `DaemonService` — daemon lifecycle management (no daemon needed)
   - `BinaryResolverService` — Go binary discovery
   - `ConfigService` (daemon config parts — `loadDaemonConfig()`, `findBinary()`, `getWorkerScriptPath()`)
   - `parallite-worker.php` — worker script (no separate process needed)
   - `bin/parallite-install`, `bin/parallite-update` — binary installers

3. **Keep** classes:
   - `ParalliteClient` — simplified, fork-only
   - `Promise` — chainable API (works with any executor)
   - `TaskService` — awaitAll/awaitMultiple (adapted for fork handles)
   - `functions.php` — global `async()`/`await()` API
   - `BenchmarkData` — benchmark tracking (adapted for fork)

4. **Simplify** `composer.json` require:
   ```json
   {
       "php": "^8.3",
       "ext-pcntl": "*",
       "ext-posix": "*"
   }
   ```
   Only PHP + built-in extensions. Zero Composer dependencies.

### IPC Options for Fork Results

| Method | Write Speed | Read Speed | Complexity | Recommendation |
|--------|-------------|------------|------------|----------------|
| **Temp file + JSON** | ~0.1ms | ~0.1ms | Low | Default — simple, debuggable, no extensions |
| **Temp file + serialize()** | ~0.05ms | ~0.05ms | Low | Faster for complex objects |
| **Pipe (socketpair)** | ~0.02ms | ~0.02ms | Medium | Best perf, no disk I/O |
| **Shared memory (shmop)** | ~0.01ms | ~0.01ms | High | Fastest but requires size limits + cleanup |

**Recommended default**: Temp file + `json_encode(JSON_UNESCAPED_SLASHES)`. Simple, debuggable (`cat /tmp/parallite_*`), and fast enough for typical payloads. Upgrade to pipes in Phase 4 if benchmarks show I/O bottleneck.

### Benchmarking Strategy

The existing benchmark feature (execution_time_ms, memory_delta_mb, memory_peak_mb, cpu_time_ms) works identically in fork mode — the child process measures itself before writing the result file.

### Windows Compatibility

`pcntl_fork()` is unavailable on Windows. Options:

1. **Sequential fallback** — run tasks one-by-one (correct but not parallel)
2. **WODVI / popen-based workers** — spawn PHP processes via `popen()` with serialized input
3. **Keep daemon mode** — preserve the Go daemon path as Windows fallback only

**Recommendation**: Sequential fallback on Windows. Most PHP parallel execution tools (ReactPHP, Amp) also don't support Windows. Document the limitation clearly.

## Migration Path

### Step 1: Add ForkExecutor alongside existing code
- New `ForkExecutor` class
- `ParalliteClient` gets `$useFork` parameter (default: `true` when pcntl available)
- All existing tests pass unchanged (daemon mode still works)
- New tests for fork mode

### Step 2: Update global functions
- `async()` and `await()` in `functions.php` auto-detect fork availability
- No API changes for users

### Step 3: Remove daemon code
- Delete `SocketService`, `DaemonService`, `BinaryResolverService`, `ConfigService` (daemon parts)
- Delete `parallite-worker.php`
- Remove `rybakit/msgpack` and `opis/closure` from dependencies
- Delete `bin/parallite-install`, `bin/parallite-update`

### Step 4: Optimize IPC (optional)
- Replace temp files with `socket_create_pair()` pipes
- Benchmark to confirm improvement
- Add shared memory option for very large result sets

## Performance Comparison (Expected)

| Metric | Go Daemon (current) | pcntl_fork (proposed) |
|--------|---------------------|----------------------|
| Startup overhead | ~5s (daemon launch) | 0s (fork is instant) |
| Per-task overhead | ~50ms (socket + msgpack + closure serialize) | ~1ms (fork + temp file write) |
| 8 parallel tasks | ~7.9s | ~0.9s |
| Dependencies | Go binary, msgpack, opis/closure, ext-sockets | ext-pcntl, ext-posix (built-in) |
| Lines of code | ~2000+ (client + daemon + worker) | ~200 (ForkExecutor only) |
| Windows support | Yes (TCP mode) | No (sequential fallback) |

## Key Design Decisions

1. **Fork inherits parent memory** — no closure serialization needed. The child process gets a copy-on-write snapshot of the parent's entire address space. This eliminates `opis/closure` entirely.

2. **No `$this` capture in child** — if a forked closure captures `$this`, the child holds a reference to the parent object graph. This can cause issues (DB connections, file handles). The ForkExecutor should document this and recommend passing primitives only (same pattern catraca uses with `SecuritySubCheck`).

3. **Child exits via SIGKILL** — prevents shutdown handlers, destructors, and DB disconnects from running in the child. The parent's resources must not be cleaned up by the child.

4. **Temp file for IPC** — pipes are faster but require blocking reads and careful ordering. Temp files are simple, debuggable, and "fast enough" for sub-10ms I/O. Upgrade later if needed.

5. **Zero Composer dependencies** — the goal is a library that requires only PHP + built-in extensions. This eliminates version conflicts, supply chain risk, and update churn.

## What About the Go Daemon?

The Go daemon (`parallite` repo) is not deleted — it remains available for:
- Windows users who need parallel execution
- Long-running server processes where daemon startup amortizes over hours
- Users who prefer the socket-based architecture

But it is no longer the default or recommended path for CLI tools. The `parallite-php` package should ship with fork-first execution and fall back to sequential on unsupported platforms.
