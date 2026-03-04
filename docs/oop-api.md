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

// Limit retries to first 3 attempts only
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

## Immutability

The `Retry` class is immutable - all methods return new instances:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

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
```

## Full Chain Example

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

$result = Retry::times(5)
    ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
    ->withMaxDelay(30_000_000)
    ->when(fn($e) => $e instanceof NetworkException)
    ->execute(fn() => $api->call());
```

## HTTP Client Example

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

class HttpClient
{
    public function request(string $url, array $options = []): Response
    {
        return Retry::times(5)
            ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
            ->withMaxDelay(30_000_000)
            ->when(function ($exception, $attempt) use ($url) {
                // Log retry attempts
                logger()->warning("Retrying request (attempt {$attempt})", [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);

                // Don't retry client errors (4xx)
                if ($exception->getCode() >= 400 && $exception->getCode() < 500) {
                    return false;
                }

                // Retry server errors (5xx) and network issues
                return true;
            })
            ->execute(fn() => $this->sendRequest($url, $options));
    }
}
```

## Database Example

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

class BankingService
{
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        Retry::times(10)
            ->withBackoff(ExponentialBackoff::milliseconds(50))
            ->withMaxDelay(5_000_000) // 5 seconds max
            ->when(fn($e) =>
                str_contains($e->getMessage(), 'Deadlock') ||
                str_contains($e->getMessage(), 'Lock wait timeout')
            )
            ->execute(function () use ($fromId, $toId, $amount) {
                DB::transaction(function () use ($fromId, $toId, $amount) {
                    // Transfer logic...
                });
            });
    }
}
```

## API Methods

| Method | Description |
|--------|-------------|
| `Retry::times(int $attempts)` | Create retry instance with max attempts |
| `Retry::withStrategy(int $attempts, BackoffStrategy $strategy)` | Create with strategy |
| `->withBackoff(BackoffStrategy $strategy)` | Add backoff strategy |
| `->withMaxDelay(int $microseconds)` | Cap maximum delay |
| `->when(Closure $condition)` | Add retry condition |
| `->execute(callable $fn)` | Execute with retry logic |
