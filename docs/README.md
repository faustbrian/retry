Retry is a comprehensive retry library for PHP 8.4+ featuring multiple backoff strategies, jitter support, and both functional and fluent OOP interfaces for building resilient applications.

## Installation

```bash
composer require cline/retry
```

## Quick Start

### Functional Style

```php
use function Cline\Retry\retry;
use Cline\Retry\Strategy\ExponentialBackoff;

// Retry an API call up to 5 times with exponential backoff
$result = retry(5, ExponentialBackoff::milliseconds(100))(
    fn() => $apiClient->fetchData()
);
```

### OOP Style

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

// Fluent interface with max delay cap
$result = Retry::times(5)
    ->withBackoff(ExponentialBackoff::seconds(1))
    ->withMaxDelay(30_000_000) // 30 seconds
    ->execute(fn() => $database->query());
```

## Features

- **8 Backoff Strategies**: Constant, Linear, Exponential, Exponential+Jitter, Fibonacci, Decorrelated Jitter, Polynomial, and Max Delay Decorator
- **Functional & OOP APIs**: Choose the style that fits your codebase
- **Conditional Retries**: Retry only on specific exceptions or conditions
- **Max Delay Caps**: Prevent exponential backoff from growing too large
- **Immutable**: All configuration methods return new instances
- **Type Safe**: Full PHP 8.4 type coverage with readonly properties

## When to Use Retry Logic

Retry logic is essential for handling transient failures:

- **Network requests** - API calls, HTTP requests, socket connections
- **Database operations** - Connection timeouts, deadlocks, lock wait timeouts
- **External services** - Rate limiting, temporary unavailability, maintenance windows
- **File operations** - Locked files, network drives, cloud storage
- **Queue processing** - Message delivery, job execution

## Next Steps

- [Functional API](./functional-api.md) - Using the `retry()` function
- [OOP API](./oop-api.md) - Fluent interface with conditional retries
- [Backoff Strategies](./backoff-strategies.md) - All 8 backoff algorithms
- [Examples](./examples.md) - Real-world usage patterns
