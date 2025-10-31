# Functional API

The `retry()` function provides a functional, composable interface for retry logic. It returns a closure that can be reused across multiple operations.

## Basic Usage

```php
use function Cline\Retry\retry;

// Create a retrier with 3 attempts
$retrier = retry(3);

// Execute operations
$result1 = $retrier(fn() => $api->fetchUser(1));
$result2 = $retrier(fn() => $api->fetchUser(2));
```

## With Backoff Strategy

```php
use Cline\Retry\Strategy\ExponentialBackoff;
use function Cline\Retry\retry;

// Using strategy instance
$backoff = ExponentialBackoff::milliseconds(100);
$retrier = retry(5, $backoff);

$result = $retrier(fn() => $api->call());
```

## With Custom Backoff Callable

```php
use function Cline\Retry\retry;

// Custom backoff logic - receives attempt number (1-indexed)
$backoff = function(int $attempt): int {
    // Return microseconds to sleep
    return $attempt * 1000 * 1000; // 1s, 2s, 3s...
};

$retrier = retry(5, $backoff);
$result = $retrier(fn() => $database->query());
```

## Closure Reusability

The returned closure is reusable and can be passed around:

```php
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use function Cline\Retry\retry;

class ApiClient
{
    private $retrier;

    public function __construct()
    {
        $this->retrier = retry(
            5,
            ExponentialJitterBackoff::milliseconds(100)
        );
    }

    public function fetchUser(int $id): array
    {
        return ($this->retrier)(fn() => $this->makeRequest("/users/{$id}"));
    }

    public function updateUser(int $id, array $data): array
    {
        return ($this->retrier)(fn() => $this->makeRequest("/users/{$id}", 'PUT', $data));
    }
}
```

## Composition with Other Functions

```php
use function Cline\Retry\retry;

// Compose with other functional utilities
$processData = fn($url) => pipe(
    $url,
    retry(3)(file_get_contents(...)),
    json_decode(...),
    fn($data) => array_filter($data, fn($item) => $item['active'])
);

$result = $processData('https://api.example.com/data');
```

## Exception Handling

The last exception is thrown if all attempts fail:

```php
use function Cline\Retry\retry;

$retrier = retry(3);

try {
    $result = $retrier(fn() => $api->call());
} catch (ApiException $e) {
    // This is the exception from the 3rd (final) attempt
    logger()->error('All retry attempts failed', [
        'message' => $e->getMessage()
    ]);
}
```

## No Backoff

Retry immediately without delays:

```php
use function Cline\Retry\retry;

// No backoff parameter = immediate retries
$retrier = retry(5);

$result = $retrier(fn() => $cache->get('key'));
```

## Timing Considerations

- Backoff callable receives 1-indexed attempt number (1, 2, 3...)
- Must return microseconds as integer
- No delay occurs after final failed attempt
- No delay occurs after successful attempt

## Backoff Callable Examples

```php
// Constant backoff (100ms)
$constant = fn(int $attempt): int => 100_000;

// Linear backoff (1s, 2s, 3s...)
$linear = fn(int $attempt): int => $attempt * 1_000_000;

// Exponential backoff (2^attempt * 100ms)
$exponential = fn(int $attempt): int => (2 ** $attempt) * 100_000;

// Fibonacci-like
$fibonacci = fn(int $attempt): int => match($attempt) {
    1 => 100_000,
    2 => 100_000,
    default => fibonacci($attempt - 1) + fibonacci($attempt - 2),
};

// With jitter
$withJitter = fn(int $attempt): int => random_int(
    0,
    (2 ** $attempt) * 100_000
);

// Capped exponential
$capped = fn(int $attempt): int => min(
    30_000_000, // 30s max
    (2 ** $attempt) * 100_000
);
```

## Advantages

- **Composable**: Works well in functional pipelines
- **Lightweight**: Simple function calls
- **Flexible**: Custom backoff logic with callables
- **Reusable**: One retrier for multiple operations

## Limitations

- No conditional retry support (use OOP API for that)
- No max delay caps (implement in custom callable or use OOP API)
- Less discoverable than fluent interface
