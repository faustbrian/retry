# OOP API

The `Retry` class provides a fluent, object-oriented interface with advanced features like conditional retries and max delay caps.

## Basic Usage

```php
use Cline\Retry\Retry;

// Simple retry with 5 attempts
$result = Retry::times(5)->execute(fn() => $api->call());

// Alternative static constructor
$retry = Retry::times(3);
$result = $retry->execute(fn() => $database->query());
```

## With Backoff Strategy

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

$result = Retry::times(5)
    ->withBackoff(ExponentialBackoff::milliseconds(100))
    ->execute(fn() => $api->call());

// Alternative: withStrategy() static method
$retry = Retry::withStrategy(
    5,
    ExponentialBackoff::milliseconds(100)
);
$result = $retry->execute(fn() => $api->call());
```

## Max Delay Caps

Prevent unbounded backoff growth:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

$result = Retry::times(10)
    ->withBackoff(ExponentialBackoff::seconds(1))
    ->withMaxDelay(30_000_000) // Cap at 30 seconds
    ->execute(fn() => $api->call());
```

## Conditional Retries

Retry only for specific exceptions or conditions:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

// Retry only on specific exception types
$result = Retry::times(5)
    ->withBackoff(ExponentialBackoff::milliseconds(100))
    ->when(fn($exception, $attempt) => $exception instanceof NetworkException)
    ->execute(fn() => $api->call());

// Don't retry on client errors (4xx)
$result = Retry::times(5)
    ->withBackoff(ExponentialBackoff::milliseconds(100))
    ->when(fn($exception, $attempt) =>
        $exception instanceof HttpException && $exception->getCode() >= 500
    )
    ->execute(fn() => $http->request());

// Retry only first 3 attempts
$result = Retry::times(5)
    ->when(fn($exception, $attempt) => $attempt <= 3)
    ->execute(fn() => $api->call());

// Complex conditions
$result = Retry::times(5)
    ->when(function($exception, $attempt) {
        // Don't retry validation errors
        if ($exception instanceof ValidationException) {
            return false;
        }

        // Don't retry after 10 attempts
        if ($attempt > 10) {
            return false;
        }

        // Retry everything else
        return true;
    })
    ->execute(fn() => $api->call());
```

## Method Chaining

All configuration methods return new instances (immutable):

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\{ExponentialBackoff, LinearBackoff};

$baseRetry = Retry::times(5);

// Each creates a new instance
$retryWithBackoff = $baseRetry->withBackoff(ExponentialBackoff::milliseconds(100));
$retryWithMax = $retryWithBackoff->withMaxDelay(30_000_000);
$retryWithCondition = $retryWithMax->when(
    fn($e) => $e instanceof NetworkException
);

// Original remains unchanged
$result1 = $baseRetry->execute(fn() => $api->call()); // No backoff
$result2 = $retryWithBackoff->execute(fn() => $api->call()); // With backoff

// Chain everything
$result = Retry::times(5)
    ->withBackoff(ExponentialBackoff::milliseconds(100))
    ->withMaxDelay(30_000_000)
    ->when(fn($e) => $e instanceof NetworkException)
    ->execute(fn() => $api->call());
```

## Immutability

The `Retry` class is immutable - all methods return new instances:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

$retry1 = Retry::times(5);
$retry2 = $retry1->withBackoff(ExponentialBackoff::milliseconds(100));
$retry3 = $retry2->withMaxDelay(30_000_000);

// $retry1, $retry2, $retry3 are all different instances
// Original $retry1 is unchanged
```

## Real-World Examples

### HTTP API with Intelligent Retries

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

class HttpClient
{
    public function request(string $url, array $options = []): Response
    {
        return Retry::times(5)
            ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
            ->withMaxDelay(30_000_000) // 30s max
            ->when(function ($exception, $attempt) {
                // Don't retry client errors (4xx)
                if ($exception instanceof ClientException) {
                    return false;
                }

                // Retry server errors (5xx)
                if ($exception instanceof ServerException) {
                    return true;
                }

                // Retry network errors
                if ($exception instanceof NetworkException) {
                    return true;
                }

                return false;
            })
            ->execute(fn() => $this->makeRequest($url, $options));
    }
}
```

### Database with Deadlock Handling

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;
use Illuminate\Database\QueryException;

class DatabaseRepository
{
    public function transaction(callable $callback): mixed
    {
        return Retry::times(10)
            ->withBackoff(ExponentialBackoff::milliseconds(50))
            ->withMaxDelay(5_000_000) // 5s max
            ->when(fn($exception, $attempt) =>
                $exception instanceof QueryException &&
                $exception->getCode() === '40001' // Deadlock
            )
            ->execute(fn() => DB::transaction($callback));
    }
}
```

### Rate Limited API

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ConstantBackoff;

class RateLimitedClient
{
    public function call(string $endpoint): array
    {
        return Retry::times(3)
            ->withBackoff(ConstantBackoff::seconds(60)) // Wait 1 minute
            ->when(fn($exception, $attempt) =>
                $exception instanceof RateLimitException
            )
            ->execute(fn() => $this->makeCall($endpoint));
    }
}
```

## Exception Handling

When all attempts fail, the last exception is thrown:

```php
use Cline\Retry\Retry;

try {
    $result = Retry::times(3)->execute(fn() => $api->call());
} catch (ApiException $e) {
    // This is the exception from the 3rd (final) attempt
    logger()->error('All retry attempts failed', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
```

## Advantages

- **Fluent Interface**: Readable, self-documenting code
- **Conditional Retries**: Fine-grained control over when to retry
- **Max Delay Caps**: Prevent unbounded growth
- **Immutable**: Safe to pass around and reuse
- **Type Safe**: Full PHP 8.4 type coverage
- **Discoverable**: IDE autocomplete for all methods

## All Methods

- `Retry::times(int $attempts): self` - Create retry with max attempts
- `Retry::withStrategy(int $attempts, BackoffStrategy $strategy): self` - Create with attempts and strategy
- `withBackoff(BackoffStrategy $strategy): self` - Add backoff strategy
- `withMaxDelay(int $microseconds): self` - Cap maximum delay
- `when(Closure $condition): self` - Add conditional retry logic
- `execute(callable $fn): mixed` - Execute callable with retry logic
