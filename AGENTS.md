# AGENTS.md — PHP Architecture & Performance Best Practices

This document captures architectural patterns, best practices, and design decisions for building maintainable, testable, and well-organized PHP applications. These principles apply to any PHP codebase — whether web APIs, CLI tools, background workers, or libraries — using any modern framework or none at all.

## Table of Contents

1. [Class Design Principles](#class-design-principles)
2. [Service Extraction](#service-extraction)
3. [Result Objects](#result-objects)
4. [Separation of Concerns](#separation-of-concerns)
5. [Dependency Injection](#dependency-injection)
6. [Code Quality Principles](#code-quality-principles)
7. [Performance Optimization](#performance-optimization)
8. [Error Handling](#error-handling)
9. [CLI-Specific Patterns](#cli-specific-patterns)
10. [Framework-Specific Notes](#framework-specific-notes)
11. [Directory Structure Examples](#directory-structure-examples)
12. [Other Considerations](#other-considerations)

---

## Design Principles

### Single Responsibility
Every class should have one reason to change. If a class handles input parsing, business logic, and output formatting, split it.

**Before (bad — mixed concerns):**
```php
class UserController
{
    public function store(Request $request): Response
    {
        // Validation
        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
        ]);

        // Business logic
        $user = new User;
        $user->email = $data['email'];
        $user->name = $data['name'];
        $user->password = bcrypt($data['password']);
        $user->save();

        // Side effect
        Mail::to($user)->send(new WelcomeEmail($user));

        // Formatting
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }
    
    public function show(Request $request): Response
    {
        // ...
    }
}
```

**After (good — thin controller, rich service):**
```php
class UserController
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function store(CreateUserRequest $request): UserResource
    {
        $user = $this->users->create($request->validated());

        return new UserResource($user);
    }
    
    public function show(Request $request): Response
    {
        // ...
    }
}
```

### Thin Layers Pattern
Every layer in your application should be thin:
- **Controllers / Commands** — Accept input, delegate, return output (≤ 1000 lines)
- **Services** — Orchestrate business operations (≤ 250 lines)
- **Repositories / Query Builders** — Abstract data access
- **Value Objects / DTOs** — Encapsulate data with validation

When any layer grows beyond its limit, extract a new class.

### Constructor Property Promotion
Use PHP 8+ constructor property promotion to reduce boilerplate:

```php
// Avoid — unnecessary repetition
class ImportService
{
    private CsvParser $parser;
    private LoggerInterface $logger;

    public function __construct(CsvParser $parser, LoggerInterface $logger)
    {
        $this->parser = $parser;
        $this->logger = $logger;
    }
}

// Good — concise
readonly class ImportService
{
    public function __construct(
        private CsvParser $parser,
        private LoggerInterface $logger,
    ) {}
}
```

### Readonly Classes vs Readonly Properties
When **all** constructor-promoted properties are `readonly`, prefer making the **entire class readonly** instead of individual properties. This reduces boilerplate and signals immutability at the class level.

**Before (bad — redundant `readonly` on every parameter):**
```php
class UserData
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly DateTimeImmutable $createdAt,
    ) {}
}
```

**After (good — class-level readonly):**
```php
readonly class UserData
{
    public function __construct(
        private string $name,
        private string $email,
        private DateTimeImmutable $createdAt,
    ) {}
}
```

**When to use `readonly class`:**
- All properties are initialized via constructor promotion
- No property needs to be mutable after construction
- The class has no dynamic properties

**When NOT to use `readonly class`:**
- Any property needs to be mutable (e.g., counters, state tracking)
- The class has non-promoted properties that must be writable
- The class extends a non-readonly parent (PHP limitation)

### Empty Constructors
Do not allow empty `__construct()` methods with zero parameters unless the constructor is private (e.g., for singletons or static factories).

```php
// Bad — pointless
class Calculator
{
    public function __construct() {}

    public static function add(int $a, int $b): int
    {
        return $a + $b;
    }
}

// Good — no constructor needed
class Calculator
{
    public static function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
```

---

## Service Extraction

### When to Extract a Service
Extract a service when you encounter any of these smells:
1. A method does not use `$this` (can be static / standalone)
2. The same logic exists in 2+ locations
3. A class exceeds 250 lines
4. A method has more than 3 levels of indentation
5. Mixed levels of abstraction in one method

### Service Categories

| Category | Responsibility | Examples |
|----------|---------------|----------|
| **Resolvers** | Locate and validate external resources | `ProjectResolver`, `ConfigResolver` |
| **Executors** | Execute a specific operation | `ImportService`, `ExportService`, `PaymentProcessor` |
| **Formatters** | Render results to output formats | `HumanFormatter`, `JsonFormatter` |
| **Runners** | Execute external processes | `ProcessRunner` |
| **Validators** | Run a single validation check | `SchemaValidator`, `FileValidator` |
| **Repositories** | Abstract data persistence | `UserRepository`, `OrderRepository` |
| **Transformers** | Convert between representations | `UserTransformer`, `ApiResource` |

### Interface Segregation
Services that are swappable should implement an interface:

```php
interface PaymentProcessorInterface
{
    public function charge(Customer $customer, Money $amount): PaymentResult;
    public function refund(string $transactionId): RefundResult;
}
```

This enables:
- Polymorphic usage (`$gateway->charge(...)`)
- Easy testing with mocks
- Future extensibility (e.g., switching from Stripe to PayPal)

---

## Result Objects

### Always Return Structured Results
Never echo output directly from services. Return structured result objects that the presentation layer formats.

**Pattern:**
```php
readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $transactionId,
        public ?string $errorMessage = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'error' => $this->errorMessage,
        ];
    }
}
```

**Benefits:**
- Multiple output formats (JSON, HTML, CLI) from the same result
- Testable assertions on result data
- Composable — results can be merged, filtered, transformed
- Type-safe — the class enforces the structure

### Mirror Existing Patterns
When creating a new result type, mirror the structure of existing result types in the project. Maintain consistency in:
- Count methods (`get*Count()`)
- Status methods (`isSuccess()`, `isPass()`)
- Serialization (`toArray()`, `toJson()`)

---

## Separation of Concerns

### Formatting is Not Business Logic
Formatters are pure functions that take a result object and return a string. They must not:
- Execute processes
- Read files
- Access the database
- Produce side effects

```php
// Good — pure formatter
class JsonFormatter
{
    public function format(ResultInterface $result): string
    {
        return json_encode($result->toArray(), JSON_THROW_ON_ERROR);
    }
}
```

### Validation is Not Business Logic
Extract validation rules into dedicated classes:
- **Form Requests** (Laravel) — `CreateUserRequest`
- **DTOs with validation** — `UserData::fromArray($input)`
- **Standalone validators** — `EmailValidator::assert($email)`

### Side Effects Should Be Explicit
If a method sends emails, writes files, or makes API calls, make it obvious:

```php
// Good — name reveals the side effect
public function createAndNotify(UserData $data): User;

// Bad — hidden side effect
public function create(UserData $data): User;  // also sends email?
```

---

## Dependency Injection

### Prefer Constructor Injection
Services should receive dependencies via constructor, not instantiate them inside methods.

**Good:**
```php
class ReportGenerator
{
    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly StorageInterface $storage,
    ) {}
}
```

**Acceptable fallback:** Default constructor arguments for simple, stateless services:
```php
public function __construct(
    private readonly LoggerInterface $logger = new NullLogger,
) {}
```

This allows instantiation without a DI container while still supporting injection.

### Avoid Service Locator Pattern
Do not pass around a "bag of services" or a container. Pass only the specific dependencies needed.

```php
// Bad — unclear dependencies
class InvoiceService
{
    public function __construct(private Container $container) {}

    public function generate(): void
    {
        $pdf = $this->container->get(PdfRenderer::class);  // hidden dependency
        // ...
    }
}

// Good — explicit dependencies
class InvoiceService
{
    public function __construct(private PdfRenderer $pdf) {}
}
```

---

## Code Quality Principles

### DRY (Don't Repeat Yourself)
Before extracting, identify duplication via tools like `phpcpd`, `b7s/catraca`. Common duplications to watch for:
- Path resolution logic
- Source directory iteration
- Box/divider rendering in formatters
- Error message formatting
- Array transformation patterns
- Database query conditions

### SRP (Single Responsibility Principle)
Each class should have one reason to change:
- **Controllers** change when request/response format changes
- **Services** change when business logic changes
- **Formatters** change when output format changes
- **Validators** change when validation criteria change
- **Repositories** change when data access logic changes
- **ENUM** values change when new states are added

### Consistent Naming
Follow these conventions:
| Pattern | Example |
|---------|---------|
| Commands | `*Command` suffix, in `Command\` namespace |
| Controllers | `*Controller` suffix |
| Services | `*Service` suffix, in `Service\` namespace |
| Repositories | `*Repository` suffix |
| Formatters | `*Formatter` suffix, in `Output\` namespace |
| Interfaces | `*Interface` suffix |
| Traits | Descriptive noun, no suffix |
| Enums | TitleCase keys: `Active`, `Pending`, `Archived` |

### Use Enums, Avoid Hardcoded Strings
Replace magic strings and hardcoded values with typed Enums. This prevents typos, enables IDE autocompletion, and makes invalid states unrepresentable.

**Before (bad — stringly typed, error-prone):**
```php
class Payment
{
    public function __construct(
        public string $status,
    ) {}
}

// Any string is accepted — typos pass silently
$payment = new Payment(status: 'pending');

if ($payment->status === 'completed') { /* ... */ }
```

**After (good — type-safe, exhaustive):**
```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}

class Payment
{
    public function __construct(
        public PaymentStatus $status,
    ) {}
}

// Invalid states are caught by the type system
$payment = new Payment(status: PaymentStatus::Pending);

if ($payment->status === PaymentStatus::Completed) { /* ... */ }
```

**Benefits:**
- **Type safety** — only valid states are representable
- **IDE support** — autocompletion prevents typos
- **Exhaustive checking** — `match` expressions enforce handling all cases:
  ```php
  $label = match ($status) {
      PaymentStatus::Pending => 'Awaiting payment',
      PaymentStatus::Completed => 'Payment received',
      PaymentStatus::Failed => 'Payment failed',
  };
  ```
- **Refactoring safety** — renaming a case updates all references

**Enum Methods for Improved Usability:**
PHP enums can define methods that encapsulate behavior directly on the cases. This keeps logic close to the data it operates on and eliminates scattered `match` expressions throughout the codebase.

```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Completed => 'Payment received',
            self::Failed => 'Payment failed',
            self::Refunded => 'Payment refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Refunded => 'blue',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Refunded], true);
    }

    public function badge(): string
    {
        return sprintf('<span class="badge bg-%s">%s</span>', $this->color(), $this->label());
    }
}

// Usage — logic lives on the enum, not scattered in views/services
$status = PaymentStatus::Completed;
echo $status->label();    // 'Payment received'
echo $status->color();    // 'green'
echo $status->badge();    // '<span class="badge bg-green">Payment received</span>'

if ($status->isFinal()) {
    // No further transitions allowed
}
```

**Static helper methods** are also useful for selects, validation, and filtering:

```php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $status): array => $carry + [$status->value => $status->label()],
            [],
        );
    }

    public static function finalStatuses(): array
    {
        return array_filter(
            self::cases(),
            static fn (self $status): bool => $status->isFinal(),
        );
    }
}

// Usage
PaymentStatus::values();          // ['pending', 'completed', 'failed', 'refunded']
PaymentStatus::options();         // ['pending' => 'Awaiting payment', ...]
PaymentStatus::finalStatuses();   // [Completed, Failed, Refunded]
```

**Benefits of enum methods:**
- **Single source of truth** — labels, colors, and behavior live on the enum itself
- **No scattered match expressions** — consumers call `$status->label()` instead of duplicating `match` blocks
- **Easy to extend** — add a new method once, all consumers benefit immediately
- **Testable in isolation** — enum methods are pure functions, trivial to unit test
- **Blade/Filament-friendly** — `PaymentStatus::options()` feeds directly into dropdowns, tables, and filters

**When to use Enums:**
- Status codes (order status, payment status, job status)
- Type discriminators (user type, notification type, event type)
- Feature flags or toggles
- Configuration values with a fixed set of options

**When NOT to use Enums:**
- Free-text user input (use strings with validation)
- Values that change at runtime (use database lookups)
- Values that need to be configurable per environment

### Strict Typing
Always use strict typing at the head of a `.php` file:
```php
<?php
declare(strict_types=1);
```

Always use explicit return type declarations and the appropriate type hints for method parameters.

### Explicit Types for Constants, Properties, and Parameters

Always declare explicit types. Every constant, property, and parameter must have a declared type. Never rely on inference or implicit coercion.

**Typed constants (PHP 8.3+):**

```php
// Bad — type inferred from value, no static analysis guarantee
const MAX_RETRIES = 3;
const API_VERSION = 'v2';

// Good — explicit type
const int MAX_RETRIES = 3;
const string API_VERSION = 'v2';
const Status DEFAULT_STATUS = Status::Pending;
const array ITEMS = ['one', 'two'];
```

**Class constants:**

```php
class ApiConfig
{
    // Bad
    public const TIMEOUT = 30;
    public const BASE_URL = 'https://api.example.com';

    // Good
    public const int TIMEOUT = 30;
    public const string BASE_URL = 'https://api.example.com';
}
```

**Typed properties:**

```php
class UserImporter
{
    // Bad — no type, accepts anything
    private $logger;
    private $timeout = 30;

    // Good — every property is typed
    private LoggerInterface $logger;
    private int $timeout = 30;
    private ?string $apiKey = null;
}
```

**Typed parameters and return types:**

```php
// Bad — untyped parameters and return
public function process($input, $options)
{
    // ...
}

// Good — every parameter typed, return type declared
public function process(
    array $input,
    ImportOptions $options,
): ImportResult {
    // ...
}
```

**Local variables (PHPDoc when ambiguous):**

PHP does not support typed local variables inside methods. When a variable's type cannot be inferred by static analysis tools, annotate it with a PHPDoc block:

```php
/** @var array<int, User> $users */
$users = $repository->findActive();

/** @var ?string $cachedValue */
$cachedValue = $cache->get('key');
```

**Why explicit types matter:**
- **Static analysis** — PHPStan and Psalm verify correctness without running code
- **IDE support** — autocompletion and inline error detection
- **Refactoring safety** — changing a type surfaces all affected call sites immediately
- **Self-documenting contracts** — signatures describe behavior without reading implementation
- **Runtime safety** — `declare(strict_types=1)` combined with typed parameters rejects invalid input immediately

### Control Structures
Always use curly braces for control structures, even if it has one line:
```php
// Good
if ($value === null) {
    return;
}

// Bad — error-prone
if ($value === null)
    return;
```

### Lambdas and Closures
Lambdas not using `$this` should be `static`:
```php
$filtered = array_filter(
    $items,
    static fn (Item $item): bool => $item->isActive(),
);
```

### Import Classes and Namespaces
Always import classes with `use` statements at the top of the file. Never use fully qualified class names (FQCN) inline in the code.

**Before (bad — hard to read, verbose):**
```php
<?php

namespace App\Service;

class UserImporter
{
    public function import(): \App\Models\User
    {
        $validator = new \App\Validators\EmailValidator();
        $repository = new \App\Repositories\UserRepository();
        // ...
    }
}
```

**After (good — clean, readable):**
```php
<?php

namespace App\Service;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Validators\EmailValidator;

class UserImporter
{
    public function import(): User
    {
        $validator = new EmailValidator();
        $repository = new UserRepository();
        // ...
    }
}
```

**Benefits:**
- Improves readability — short names are easier to scan
- Makes refactoring safer — change the import, not every inline reference
- Reduces visual noise and line length
- Helps IDEs provide better autocompletion and navigation

**Exception:** FQCN is acceptable inside PHPDoc blocks when documenting generic types or relationship return types for static analysis tools.

---

## Performance Optimization

### Import Native Functions Explicitly
Always import native PHP functions with `use function` to bypass namespace resolution overhead:

```php
<?php

namespace App\Service;

use function array_filter;
use function array_map;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function str_contains;
use function strlen;
use function trim;

class ImportService
{
    public function process(array $lines): array
    {
        return array_filter(
            array_map(static fn (string $line): string => trim($line), $lines),
            static fn (string $line): bool => str_contains($line, '@'),
        );
    }
}
```

**Why:** PHP resolves unqualified function calls in namespaced code by first checking the current namespace, then falling back to the global namespace. Explicit imports eliminate this lookup.

### Prefer Static Methods and Closures
When a method does not use `$this`, declare it `static`:

```php
// Good — no $this binding overhead
public static function normalize(string $input): string
{
    return strtolower(trim($input));
}

// Good — static closure
$normalized = array_map(
    static fn (string $line): string => strtolower(trim($line)),
    $lines,
);
```

**Why:** Non-static closures capture `$this` by default, creating a reference cycle and preventing garbage collection.

### Use Strict Comparison (`===`)
Always prefer `===` and `!==` over `==` and `!=`:

```php
if ($value === null) { /* ... */ }      // Fast type check
if ($status === Status::Active) { /* ... */ }
```

**Why:** Loose comparison triggers type juggling and multiple type checks internally.

### Use Modern String Functions
Prefer PHP 8+ string functions over `strpos`/`substr` combinations:

```php
// Good — single operation, no comparison
if (str_contains($haystack, $needle)) { /* ... */ }
if (str_starts_with($path, '/')) { /* ... */ }
if (str_ends_with($file, '.php')) { /* ... */ }

// Avoid — two operations
if (strpos($haystack, $needle) !== false) { /* ... */ }
```

### Pre-allocate Arrays and Avoid `count()` in Loops
```php
// Avoid — count() called on every iteration
for ($i = 0; $i < count($items); $i++) { /* ... */ }

// Good — cache the count
$count = count($items);
for ($i = 0; $i < $count; $i++) { /* ... */ }

// Better — foreach is optimized for arrays
foreach ($items as $item) { /* ... */ }

// Best — pre-allocate when size is known
$result = [];
$result = array_fill(0, count($items), null);
foreach ($items as $i => $item) {
    $result[$i] = transform($item);
}
```

### Buffer Output
Minimize I/O by collecting output in memory and writing once:

```php
// Avoid — multiple I/O calls
foreach ($results as $result) {
    $output->writeln(format($result));
}

// Good — single write
$lines = [];
foreach ($results as $result) {
    $lines[] = format($result);
}
$output->write(implode("\n", $lines));
```

### Cache Expensive Lookups
```php
// Avoid — repeated disk or network calls
foreach ($files as $file) {
    if (file_exists($file)) { /* ... */ }
}

// Good — cache results
$cache = [];
foreach ($files as $file) {
    $cache[$file] ??= file_exists($file);
    if ($cache[$file]) { /* ... */ }
}
```

### Use Generators for Large Datasets
```php
// Memory-efficient streaming
public function readLines(string $path): \Generator
{
    $handle = fopen($path, 'r');
    while ($line = fgets($handle)) {
        yield trim($line);
    }
    fclose($handle);
}

// Usage — processes one line at a time
foreach ($this->readLines($path) as $line) {
    $this->process($line);
}
```

### JSON Encoding Flags
```php
// Fastest — avoid pretty-print and escaping in production
json_encode($data, JSON_UNESCAPED_SLASHES);

// Debugging only — consumes more memory and CPU
json_encode($data, JSON_PRETTY_PRINT);
```

### Boolean Condition Ordering for performance
In `&&` and `||` expressions, cheaper conditions should come first so PHP can short-circuit and skip expensive evaluations.

**Cost model:**

| Cost | Expression types | Examples |
|------|-----------------|----------|
| **0** | Variables, literals, guards | `$x`, `true`, `isset($x)`, `empty($y)`, `is_array($z)`, `is_numeric($n)`, `instanceof Foo` |
| **1** | Property/array access, comparisons, cheap functions | `$obj->prop`, `$arr['key']`, `$a === $b`, `$x > 0`, `count($arr)`, `strlen($s)`, `str_contains($h, $n)` |
| **2** | Default (casts, closures, non-guard func calls) | `(int) $x`, `fn() => true`, `someHelper()` |
| **3** | Method calls, static calls, `new`, assignments | `$this->method()`, `Foo::bar()`, `new Entity()`, `$x = expensive()` |

**Rules:**

1. **Guards always come first** — `isset`, `empty`, and `is_*` checks should be the leftmost conditions. They are the cheapest and prevent errors in subsequent checks.
   ```php
   // Good — guard first
   if (isset($array['key']) && is_array($array['key'])) {}
   
   // Bad — expensive check before guard
   if (is_array($array['key']) && isset($array['key'])) {}
   ```

2. **Property and array access come after guards** — Accessing properties or array elements costs 1, but only if the base expression is cheap. `$this->foo` costs 1, but `tryIt()->data` costs 3 because of the method call.
   ```php
   // Good — cheap property access after guard
   if (isset($record->data) && $record->data !== []) {}
   
   // Bad — method call before guard
   if ($record->getData() !== [] && isset($record->data)) {}
   ```

3. **Empty array literals `[]` cost 0** — They are literals, just like `true` or `null`.
   ```php
   // Good — literal comparison is cheap
   if ($options !== [] && isset($first['label']) && is_array($first)) {}
   ```

4. **Never reorder expressions with side effects** — Functions like `mkdir`, `unlink`, `file_put_contents`, etc. must stay in their original position because their execution order matters.

5. **Nested `&&` chains are evaluated left-to-right** — Each adjacent pair is checked independently. Fix iteratively.
   ```php
   // Original: all three conditions are in suboptimal order
   if ($expensive() && $cheap && isset($x)) {}
   
   // After first fix:
   if ($cheap && $expensive() && isset($x)) {}
   
   // After second fix:
   if ($cheap && isset($x) && $expensive()) {}
   ```
   
---

## Error Handling

### Graceful Degradation
When a dependency is not available, skip the operation rather than fail:

```php
$tool = $resolver->resolve('optional-tool');
if ($tool === null) {
    return new TaskResult(
        label: $this->getLabel(),
        skipped: true,
        message: 'skipped (install optional-tool)',
    );
}
```

### Return Meaningful Exit Codes (CLI/GitHub Actions)
| Code | Meaning |
|------|---------|
| `0` | Success / all operations passed |
| `1` | Failure / one or more operations failed |

### Fail Fast
Detect and report errors as early as possible — at the entry point of a method, not buried deep in logic. When something is wrong, fail immediately with a clear message. Do not continue execution hoping for the best.

**Core principle:** Every method should validate its preconditions first, then proceed with the happy path at the top level of indentation.

**Before (bad — deeply nested, hides the happy path):**
```php
public function processOrder(OrderData $data): OrderResult
{
    if ($data->items !== []) {
        $customer = $this->customerRepo->find($data->customerId);
        if ($customer !== null) {
            if ($customer->isActive()) {
                $total = $this->calculator->calculate($data->items);
                if ($total > 0) {
                    $payment = $this->payment->charge($customer, $total);
                    if ($payment->isSuccess()) {
                        return new OrderResult(success: true, orderId: $payment->transactionId);
                    }
                    return new OrderResult(success: false, error: 'Payment failed');
                }
                return new OrderResult(success: false, error: 'Invalid total');
            }
            return new OrderResult(success: false, error: 'Customer inactive');
        }
        return new OrderResult(success: false, error: 'Customer not found');
    }
    return new OrderResult(success: false, error: 'No items');
}
```

**After (good — guard clauses, flat happy path):**
```php
public function processOrder(OrderData $data): OrderResult
{
    if ($data->items === []) {
        return new OrderResult(success: false, error: 'No items');
    }

    $customer = $this->customerRepo->find($data->customerId);
    if ($customer === null) {
        return new OrderResult(success: false, error: 'Customer not found');
    }

    if (!$customer->isActive()) {
        return new OrderResult(success: false, error: 'Customer inactive');
    }

    $total = $this->calculator->calculate($data->items);
    if ($total <= 0) {
        return new OrderResult(success: false, error: 'Invalid total');
    }

    $payment = $this->payment->charge($customer, $total);
    if (!$payment->isSuccess()) {
        return new OrderResult(success: false, error: 'Payment failed');
    }

    return new OrderResult(success: true, orderId: $payment->transactionId);
}
```

**Benefits of fail fast:**
- **Flat indentation** — the happy path stays at the top level, no nesting
- **Readable top-to-bottom** — preconditions first, then business logic
- **Early exit** — each guard clause returns immediately, no mental stacking
- **Easier debugging** — failures surface at the exact point they occur
- **Fewer bugs** — you never accidentally proceed with invalid state

**Guard Clause Patterns:**

```php
// Null check
if ($value === null) {
    return $fallback;
}

// Empty collection
if ($items === []) {
    return new Result(success: false, error: 'No items provided');
}

// Invalid state
if (!$this->isReady()) {
    throw new InvalidStateException('Service not initialized');
}

// Missing dependency
$tool = $this->resolver->resolve('required-tool');
if ($tool === null) {
    return new TaskResult(skipped: true, message: 'Missing required-tool');
}

// Authorization
if (!$user->can('edit', $resource)) {
    throw new AuthorizationException('Not allowed');
}
```

**When to throw vs return:**
- **Throw** for programmer errors and invariant violations (invalid arguments, impossible states)
- **Return** for expected business failures (customer not found, payment declined, missing optional dependency)
- **Skip** for optional operations that can be safely bypassed (tool not installed, feature disabled)

### Validate Early
Resolve and validate inputs before doing any work — a specific application of fail fast at the entry point:

```php
$projectRoot = $this->resolveProjectRoot($input, $output);
if ($projectRoot === null) {
    return Command::FAILURE;
}
```

### Typed Exceptions
Use specific exception types rather than generic `\Exception`:

```php
class ValidationException extends \RuntimeException {}
class NotFoundException extends \RuntimeException {}
class PaymentFailedException extends \RuntimeException {}
```

---

## CLI-Specific Patterns

### Thin Command Pattern
Commands must be **ultra-thin orchestrators**. They should:
- Accept input and resolve context (project root, arguments, options)
- Delegate all business logic to dedicated services
- Format and output results
- Return exit codes

**Maximum recommended command length**: 60 lines. If a command exceeds this, extract logic into a service.

### Shared Command Concerns
Extract cross-cutting concerns into a trait or base class:
- `--path` / `--format` / `--plain` option definitions
- Project root resolution
- Result formatting

Never duplicate option definitions or path resolution logic across commands.

### Support All Output Formats
Every command that produces structured output should support:
- `human` — terminal-friendly with ANSI colors (default)
- `human` + `--plain` — no ANSI colors
- `json` — compact JSON for piping
- `json-pretty` — formatted JSON for debugging
- `github` — `::error::`, `::warning::`, `::group::` annotations (if applicable)

### Command Auto-Discovery
The application entry point should auto-discover commands from a designated directory instead of manually registering them.

**Requirements for auto-discovered commands:**
1. Located in the commands directory (e.g., `src/Command/`)
2. Class name ends with `Command`
3. Extends the framework's base Command class
4. Not abstract
5. Has the framework's command attribute (e.g., `#[AsCommand]`)

**Benefits:**
- Adding a new command requires creating one file — no entry point changes
- Eliminates merge conflicts in the entry point
- Self-documenting: all commands live in one place

---

## Framework-Specific Notes

### Symfony Console
- Use `#[AsCommand]` attributes for routing
- Register commands via `Application::add()` or auto-discovery
- Leverage `SymfonyStyle` for interactive I/O
- Use `InputOption` and `InputArgument` for type-safe input

### Laravel
- **Commands**: Place in `app/Console/Commands/`, use `php artisan make:command`
  - Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior
- **Controllers**: Keep thin, delegate to services
- **Models**: Use `casts()` method over `$casts` property (Laravel 12+)
  - When creating new models, create useful factories and seeders for them too
- **Database**: Avoid `DB::`; prefer `Model::query()`. Prevent N+1 with eager loading
  - When modifying a column, the migration must include all the attributes previously defined on the column. Otherwise, they will be dropped and lost.
  - Laravel 12+ allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10)`
- **Relationships**: Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins
- **Routing**: Prefer named routes and the `route()` function; never hardcode URLs
- **Queue**: Use `ShouldQueue` for time-consuming operations
- **Auth**: Use built-in gates, policies, Sanctum
- **Code Style**: Run `vendor/bin/pint --dirty --format agent` before committing. Do not run `--test` mode — simply run it to fix formatting issues
- **Tests**: All tests must be written using Pest. Use `php artisan make:test --pest {name}`
  - Tests should test all the happy paths, failure paths, and weird paths
  - To run all tests: `php artisan test --compact`
  - To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file)
  - Use datasets in Pest to simplify tests that have a lot of duplicated data
  - Mocking can be very helpful when appropriate
  - Browser testing is incredibly powerful and useful for this project. Browser tests should live in `tests/Browser/`
  - You must not remove any tests or test files from the tests directory without approval
  - Ensure that tests are running in a test environment and on a test database. Never delete the project database
  - Check documentation when needed: https://pestphp.com/docs/

### Tempest Console
- Use `#[ConsoleCommand]` attributes
- Leverage `HasConsole` trait for semantic output (`success()`, `error()`)
- Use middleware for cross-cutting concerns
- Prefer constructor injection via the framework's container

### Generic PHP (no framework)
- Use `symfony/console` as the de-facto standard
- Build a minimal `Application` bootstrap in `bin/` or `cli`
- Implement your own auto-discovery or manually register commands
- Use composer autoloading (`vendor/autoload.php`)

---

## Other Considerations

### General Rules
- Don't include any superfluous PHP Annotations, except ones that start with `@` for typing variables
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.
- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed
- Always add the return type for methods

### PHPDoc for Model Properties
Every model/entity class MUST declare all its properties in a class-level PHPDoc block with correct types. This enables static analysis tools (PHPStan, Psalm) to understand dynamic properties and provides IDE autocompletion.

**Good — fully documented model:**
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Model
{
    protected $fillable = ['name', 'email'];
}
```

**Bad — no PHPDoc, static analysis is blind:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email'];
}
```

**Rules:**
- Include `@property` for every database column, including timestamps and soft-delete columns
- Use nullable types when a column can be null: `@property ?string $deleted_at`
- Use union types for columns with multiple possible types: `@property int|string $status`
- Use full FQCN for non-scalar types: `@property \Carbon\Carbon $created_at`, `@property \App\Models\Company $company`
- For relationships, document the return type on the method itself, not as a property:
  ```php
  use Illuminate\Database\Eloquent\Relations\BelongsTo;

  /**
   * @return BelongsTo<Company, $this>
   */
  public function company(): BelongsTo
  {
      return $this->belongsTo(Company::class);
  }
  ```

This applies to all ORM models, DTOs, and any class with dynamic or non-promoted properties.

### FilamentPHP
- Always use Filament-specific Artisan commands to create files. Find available commands with the `php artisan --help` command
- Patterns:
  - Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values
  - Use `Get $get` to read other form field values for conditional logic, like `->visible(fn (Get $get): bool => $get('type') === 'business')`
  - Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates
  - Compose layout by nesting `Section` and `Grid`. Children need explicit `->columnSpan()` or `->columnSpanFull()`
  - Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name
  - Use `state()` with a `Closure` to compute derived column values
  - Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic
  - **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields
  - **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed
  - **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or changed modifiers that must be preserved
    - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
    - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
    - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes
- Correct Namespaces
  - Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
  - Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
  - Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
  - Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
  - Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
  - Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
  - Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
  - Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)
