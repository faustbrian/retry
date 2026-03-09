## Table of Contents

1. [Overview](#doc-docs-readme) (`docs/README.md`)
2. [Backoff Strategies](#doc-docs-backoff-strategies) (`docs/backoff-strategies.md`)
3. [Configuration](#doc-docs-configuration) (`docs/configuration.md`)
4. [Examples](#doc-docs-examples) (`docs/examples.md`)
5. [Functional Api](#doc-docs-functional-api) (`docs/functional-api.md`)
6. [Oop Api](#doc-docs-oop-api) (`docs/oop-api.md`)
<a id="doc-docs-readme"></a>

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

- [Functional API](#doc-docs-functional-api) - Using the `retry()` function
- [OOP API](#doc-docs-oop-api) - Fluent interface with conditional retries
- [Backoff Strategies](#doc-docs-backoff-strategies) - All 8 backoff algorithms
- [Examples](#doc-docs-examples) - Real-world usage patterns

<a id="doc-docs-backoff-strategies"></a>

Retry provides 8 different backoff strategies to handle various retry scenarios. Each strategy calculates delays differently to suit specific use cases.

## Constant Backoff

Fixed delay between all retry attempts. Best for scenarios where you want predictable, consistent delays.

```php
use Cline\Retry\Strategy\ConstantBackoff;

// 100ms delay between each retry
$backoff = ConstantBackoff::milliseconds(100);

// 2 second delay between each retry
$backoff = ConstantBackoff::seconds(2);

// Direct microseconds
$backoff = new ConstantBackoff(50_000); // 50ms
```

**Use when:** Rate limiting requires consistent intervals, or testing retry logic.

## Linear Backoff

Delays increase linearly with each attempt (1×, 2×, 3×, 4×...).

```php
use Cline\Retry\Strategy\LinearBackoff;

// 100ms, 200ms, 300ms, 400ms...
$backoff = LinearBackoff::milliseconds(100);

// 1s, 2s, 3s, 4s...
$backoff = LinearBackoff::seconds(1);
```

**Use when:** You want gradual backoff without explosive growth. Good for moderate load scenarios.

## Exponential Backoff

Delays grow exponentially with configurable multiplier (default 2.0).

```php
use Cline\Retry\Strategy\ExponentialBackoff;

// Default 2x multiplier: 100ms, 200ms, 400ms, 800ms...
$backoff = ExponentialBackoff::milliseconds(100);

// Custom 3x multiplier: 100ms, 300ms, 900ms, 2700ms...
$backoff = ExponentialBackoff::milliseconds(100, 3.0);

// Fractional multiplier: 100ms, 150ms, 225ms, 337.5ms...
$backoff = ExponentialBackoff::milliseconds(100, 1.5);
```

**Use when:** Dealing with cascading failures or need to back off quickly. Standard for most retry scenarios.

## Exponential Backoff with Jitter

Adds randomness to exponential backoff to prevent thundering herd problem.

```php
use Cline\Retry\Strategy\ExponentialJitterBackoff;

// Returns random value between 0 and exponential max
// Attempt 1: 0 to 100ms
// Attempt 2: 0 to 200ms
// Attempt 3: 0 to 400ms
$backoff = ExponentialJitterBackoff::milliseconds(100);

// With custom multiplier
$backoff = ExponentialJitterBackoff::milliseconds(100, 3.0);
```

**Use when:** Multiple clients retry simultaneously. Prevents synchronized retry storms. **Recommended for most production scenarios.**

## Fibonacci Backoff

Delays follow the Fibonacci sequence (1, 1, 2, 3, 5, 8, 13, 21...).

```php
use Cline\Retry\Strategy\FibonacciBackoff;

// 100ms, 100ms, 200ms, 300ms, 500ms, 800ms...
$backoff = FibonacciBackoff::milliseconds(100);

// 1s, 1s, 2s, 3s, 5s, 8s...
$backoff = FibonacciBackoff::seconds(1);
```

**Use when:** You want exponential-like growth but slower than pure exponential. Good balance between aggressive and conservative backoff.

## Decorrelated Jitter (AWS Style)

Stateful algorithm that decorrelates retries across clients using previous delay.

```php
use Cline\Retry\Strategy\DecorrelatedJitterBackoff;

// Random between base (100ms) and 3× previous delay, capped at max (30s)
$backoff = DecorrelatedJitterBackoff::milliseconds(100, 30_000);

// Base 1s, max 60s
$backoff = DecorrelatedJitterBackoff::seconds(1, 60);
```

**Formula:** `random(base, min(max, previous_delay * 3))`

**Use when:** Following AWS best practices for distributed systems. Excellent for preventing synchronized retries at scale.

## Polynomial Backoff

Delays grow by polynomial degree (default quadratic: attempt²).

```php
use Cline\Retry\Strategy\PolynomialBackoff;

// Quadratic (degree 2): 100ms, 400ms, 900ms, 1600ms...
$backoff = PolynomialBackoff::milliseconds(100);

// Cubic (degree 3): 100ms, 800ms, 2700ms, 6400ms...
$backoff = PolynomialBackoff::milliseconds(100, 3);

// Linear (degree 1): same as LinearBackoff
$backoff = PolynomialBackoff::milliseconds(100, 1);
```

**Use when:** Need faster growth than exponential or want fine-tuned polynomial curves.

## Max Delay Decorator

Wraps any strategy to cap maximum delay.

```php
use Cline\Retry\Strategy\{ExponentialBackoff, MaxDelayDecorator};

// Exponential backoff but never exceed 5 seconds
$backoff = new MaxDelayDecorator(
    ExponentialBackoff::milliseconds(100),
    5_000_000 // 5 seconds in microseconds
);

// Factory methods
$backoff = MaxDelayDecorator::milliseconds(
    ExponentialBackoff::milliseconds(100),
    5000 // 5 seconds in milliseconds
);

$backoff = MaxDelayDecorator::seconds(
    ExponentialBackoff::seconds(1),
    30 // 30 seconds
);
```

**Use when:** Need to prevent unbounded growth in any strategy. Essential for production systems with SLA requirements.

## Strategy Comparison

| Strategy | Growth Rate | Jitter | Use Case |
|----------|-------------|--------|----------|
| Constant | None | No | Testing, rate limits |
| Linear | Slow | No | Moderate load |
| Exponential | Fast | No | Cascading failures |
| Exp + Jitter | Fast | Yes | **Production default** |
| Fibonacci | Medium | No | Balanced growth |
| Decorrelated | Variable | Yes | AWS-style distributed |
| Polynomial | Configurable | No | Custom curves |
| Max Delay | Varies | Depends | Cap any strategy |

## Choosing the Right Strategy

1. **Start with ExponentialJitterBackoff** - Works well in 90% of cases
2. **Add MaxDelayDecorator** - Prevent unbounded delays
3. **Use DecorrelatedJitter** - If building AWS-compatible services
4. **Use Constant** - Only for testing or very specific rate limits
5. **Avoid Linear/Fibonacci** - Unless you have specific growth requirements

## Delay Growth Visualization

```
Attempt:    1      2      3      4      5      6

Constant:   100    100    100    100    100    100
Linear:     100    200    300    400    500    600
Exponential:100    200    400    800   1600   3200
Fibonacci:  100    100    200    300    500    800
Polynomial: 100    400    900   1600   2500   3600  (degree 2)
```

*All values in milliseconds with 100ms base*

<a id="doc-docs-configuration"></a>

Retry includes a Laravel service provider that publishes a configuration file for setting default retry behavior.

## Publishing the Config

```bash
php artisan vendor:publish --provider="Cline\Retry\RetryServiceProvider"
```

This creates `config/retry.php`:

## Configuration Options

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Retry Attempts
    |--------------------------------------------------------------------------
    |
    | The maximum number of retry attempts before giving up. Setting this too
    | high may result in excessive delays, while too low may cause premature
    | failures on transient errors.
    |
    */
    'max_attempts' => 3,

    /*
    |--------------------------------------------------------------------------
    | Maximum Delay
    |--------------------------------------------------------------------------
    |
    | The maximum delay (in microseconds) between retry attempts. If a backoff
    | strategy calculates a delay greater than this value, it will be capped.
    | Set to null for unlimited delays. Default is 60 seconds.
    |
    */
    'max_delay_microseconds' => 60_000_000, // 60 seconds

    /*
    |--------------------------------------------------------------------------
    | Default Backoff Strategy
    |--------------------------------------------------------------------------
    |
    | The default backoff strategy for retry operations. Each strategy has
    | its own configuration in the "strategies" array below.
    |
    | Supported: "exponential", "exponential_jitter", "decorrelated_jitter",
    |            "linear", "constant", "fibonacci", "polynomial", "none"
    |
    */
    'default_strategy' => 'exponential',

    /*
    |--------------------------------------------------------------------------
    | Backoff Strategy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure settings for each backoff strategy. Only the configuration
    | for the selected default strategy will be used.
    |
    */
    'strategies' => [
        'exponential' => [
            'base_microseconds' => 1_000_000, // 1 second
            'multiplier' => 2.0,
        ],

        'exponential_jitter' => [
            'base_microseconds' => 1_000_000,
            'multiplier' => 2.0,
        ],

        'decorrelated_jitter' => [
            'base_microseconds' => 1_000_000,
            'max_microseconds' => 60_000_000, // 60 seconds
        ],

        'linear' => [
            'base_microseconds' => 1_000_000,
        ],

        'constant' => [
            'delay_microseconds' => 1_000_000,
        ],

        'fibonacci' => [
            'base_microseconds' => 1_000_000,
        ],

        'polynomial' => [
            'base_microseconds' => 1_000_000,
            'degree' => 2,
        ],
    ],
];
```

## Strategy Details

### Exponential

Calculates delay as: `base × (multiplier ^ attempt)`

Example with defaults: 1s, 2s, 4s, 8s, 16s...

### Exponential Jitter

Like exponential but adds randomness to prevent thundering herd problems in distributed systems.

### Decorrelated Jitter

AWS-recommended strategy using previous delay to calculate next delay with randomness. Provides better distribution than standard jitter.

### Linear

Calculates delay as: `base × attempt`

Example: 1s, 2s, 3s, 4s, 5s...

### Constant

Uses the same delay between all retry attempts. Best for predictable, uniform intervals.

### Fibonacci

Delays follow the Fibonacci sequence: 1, 1, 2, 3, 5, 8, 13...

Provides a balance between exponential and linear growth.

### Polynomial

Calculates delay as: `base × (attempt ^ degree)`

A degree of 2 gives quadratic growth, higher degrees increase faster.

## Time Conversions

The configuration uses microseconds. Common conversions:

| Time | Microseconds |
|------|--------------|
| 1ms | 1,000 |
| 10ms | 10,000 |
| 100ms | 100,000 |
| 1 second | 1,000,000 |
| 10 seconds | 10,000,000 |
| 30 seconds | 30,000,000 |
| 1 minute | 60,000,000 |

## Environment Variables

Override configuration via environment:

```env
RETRY_MAX_ATTEMPTS=5
RETRY_MAX_DELAY=30000000
RETRY_DEFAULT_STRATEGY=exponential_jitter
```

Update your config to use these:

```php
return [
    'max_attempts' => env('RETRY_MAX_ATTEMPTS', 3),
    'max_delay_microseconds' => env('RETRY_MAX_DELAY', 60_000_000),
    'default_strategy' => env('RETRY_DEFAULT_STRATEGY', 'exponential'),
    // ...
];
```

<a id="doc-docs-examples"></a>

Real-world examples demonstrating retry patterns in various scenarios.

## HTTP API Client

A production-ready HTTP client with intelligent retry logic:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ResilientHttpClient
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
    }

    public function get(string $url, array $options = []): array
    {
        return Retry::times(5)
            ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
            ->withMaxDelay(30_000_000) // 30 seconds
            ->when(function ($exception, $attempt) use ($url) {
                if (!$exception instanceof RequestException) {
                    return false;
                }

                $code = $exception->getCode();

                // Don't retry client errors (4xx)
                if ($code >= 400 && $code < 500) {
                    return false;
                }

                // Retry server errors (5xx)
                if ($code >= 500) {
                    logger()->warning("Retrying request (attempt {$attempt})", [
                        'url' => $url,
                        'code' => $code,
                    ]);
                    return true;
                }

                // Retry connection errors
                return true;
            })
            ->execute(fn() => $this->client->get($url, $options)->getBody()->getContents());
    }
}
```

## Database Transaction with Deadlock Handling

Automatically retry transactions on deadlock:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;
use Illuminate\Support\Facades\DB;

class BankingService
{
    public function transfer(int $fromAccountId, int $toAccountId, float $amount): void
    {
        Retry::times(10)
            ->withBackoff(ExponentialBackoff::milliseconds(50))
            ->withMaxDelay(5_000_000) // 5 seconds
            ->when(fn($e) =>
                str_contains($e->getMessage(), 'Deadlock') ||
                str_contains($e->getMessage(), 'Lock wait timeout')
            )
            ->execute(function () use ($fromAccountId, $toAccountId, $amount) {
                DB::transaction(function () use ($fromAccountId, $toAccountId, $amount) {
                    // Lock accounts in consistent order to prevent deadlocks
                    $ids = [$fromAccountId, $toAccountId];
                    sort($ids);

                    $accounts = Account::whereIn('id', $ids)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $fromAccount = $accounts[$fromAccountId];
                    $toAccount = $accounts[$toAccountId];

                    if ($fromAccount->balance < $amount) {
                        throw new InsufficientFundsException();
                    }

                    $fromAccount->balance -= $amount;
                    $toAccount->balance += $amount;

                    $fromAccount->save();
                    $toAccount->save();
                });
            });
    }
}
```

## Queue Job Processor

Laravel queue job with retry logic:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $orderId,
        private float $amount
    ) {}

    public function handle(PaymentGateway $gateway): void
    {
        Retry::times(5)
            ->withBackoff(ExponentialBackoff::seconds(2))
            ->withMaxDelay(300_000_000) // 5 minutes
            ->when(function ($exception, $attempt) {
                // Don't retry validation errors
                if ($exception instanceof ValidationException) {
                    logger()->error('Payment validation failed', [
                        'order_id' => $this->orderId,
                        'error' => $exception->getMessage(),
                    ]);
                    return false;
                }

                // Don't retry insufficient funds
                if ($exception instanceof InsufficientFundsException) {
                    return false;
                }

                // Retry transient failures
                logger()->info("Retrying payment (attempt {$attempt})", [
                    'order_id' => $this->orderId,
                ]);
                return true;
            })
            ->execute(fn() => $gateway->charge($this->orderId, $this->amount));
    }
}
```

## File Upload to S3

Upload files with automatic retry on failure:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class FileUploader
{
    public function __construct(private S3Client $s3) {}

    public function upload(string $localPath, string $bucket, string $key): array
    {
        return Retry::times(5)
            ->withBackoff(ExponentialJitterBackoff::seconds(1))
            ->withMaxDelay(60_000_000) // 1 minute
            ->when(function ($exception, $attempt) {
                if (!$exception instanceof AwsException) {
                    return false;
                }

                $errorCode = $exception->getAwsErrorCode();

                // Don't retry on permanent errors
                if (in_array($errorCode, ['NoSuchBucket', 'AccessDenied'])) {
                    return false;
                }

                // Retry on throttling
                if ($errorCode === 'RequestThrottled') {
                    logger()->info("S3 throttled, retrying (attempt {$attempt})");
                    return true;
                }

                // Retry on 5xx errors
                return $exception->getStatusCode() >= 500;
            })
            ->execute(fn() => $this->s3->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'SourceFile' => $localPath,
                'ServerSideEncryption' => 'AES256',
            ])->toArray());
    }
}
```

## External API with Rate Limiting

Handle rate-limited third-party APIs:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\DecorrelatedJitterBackoff;

class WeatherApiClient
{
    private string $apiKey;
    private string $baseUrl = 'https://api.weather.example.com';

    public function getForecast(string $city): array
    {
        return Retry::times(5)
            ->withBackoff(DecorrelatedJitterBackoff::milliseconds(100, 30_000))
            ->when(function ($exception, $attempt) {
                // Rate limit - wait longer
                if ($exception->getCode() === 429) {
                    logger()->info('Rate limited by weather API', [
                        'attempt' => $attempt,
                    ]);
                    return true;
                }

                // Service unavailable - retry
                if ($exception->getCode() === 503) {
                    return true;
                }

                // Timeout - retry
                if ($exception instanceof TimeoutException) {
                    return true;
                }

                return false;
            })
            ->execute(function () use ($city) {
                $response = file_get_contents(
                    "{$this->baseUrl}/forecast?city={$city}&key={$this->apiKey}",
                    false,
                    stream_context_create([
                        'http' => ['timeout' => 10]
                    ])
                );

                return json_decode($response, true);
            });
    }
}
```

## Microservice Communication

Resilient communication between microservices:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use GuzzleHttp\Client;

class PaymentServiceClient
{
    private Client $client;
    private CircuitBreaker $circuitBreaker;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => config('services.payment.url'),
            'timeout' => 30,
        ]);
    }

    public function createPayment(array $data): array
    {
        return Retry::times(3)
            ->withBackoff(ExponentialJitterBackoff::milliseconds(200))
            ->withMaxDelay(10_000_000) // 10 seconds
            ->when(function ($exception, $attempt) {
                // Circuit breaker check
                if ($this->circuitBreaker->isOpen()) {
                    logger()->error('Payment service circuit breaker open');
                    return false;
                }

                // Track failure
                $this->circuitBreaker->recordFailure();

                // Don't retry validation errors
                if ($exception->getCode() >= 400 && $exception->getCode() < 500) {
                    return false;
                }

                logger()->warning("Payment service retry (attempt {$attempt})", [
                    'error' => $exception->getMessage(),
                ]);

                return true;
            })
            ->execute(function () use ($data) {
                $response = $this->client->post('/payments', [
                    'json' => $data,
                    'headers' => [
                        'X-Idempotency-Key' => $data['idempotency_key'],
                    ],
                ]);

                $this->circuitBreaker->recordSuccess();

                return json_decode($response->getBody(), true);
            });
    }
}
```

## Cache-Aside Pattern

Implement cache-aside pattern with retry:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\LinearBackoff;

class UserRepository
{
    public function find(int $id): User
    {
        $cacheKey = "user:{$id}";

        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Fetch from database with retry
        $user = Retry::times(3)
            ->withBackoff(LinearBackoff::milliseconds(100))
            ->execute(fn() => User::findOrFail($id));

        // Store in cache with retry
        Retry::times(3)
            ->withBackoff(LinearBackoff::milliseconds(50))
            ->when(fn($e) => $e instanceof RedisException)
            ->execute(fn() => $this->cache->put($cacheKey, $user, 3600));

        return $user;
    }
}
```

## Functional Pipeline

Using retry in functional pipelines:

```php
use function Cline\Retry\retry;
use Cline\Retry\Strategy\ExponentialBackoff;

// Create reusable retrier
$retrier = retry(5, ExponentialBackoff::milliseconds(100));

// Use in pipeline
function processDataFeed(string $url): array
{
    global $retrier;

    return pipe(
        $url,
        $retrier(file_get_contents(...)),
        json_decode(...),
        fn($data) => array_filter($data, fn($item) => $item['active']),
        fn($data) => array_map(fn($item) => [
            'id' => $item['id'],
            'name' => $item['name'],
            'processed_at' => time(),
        ], $data)
    );
}
```

## Common Patterns Summary

| Use Case | Strategy | Max Delay | Retry Condition |
|----------|----------|-----------|-----------------|
| HTTP APIs | ExponentialJitter | 30s | Don't retry 4xx |
| Databases | Exponential | 5s | Only deadlocks |
| File Systems | Linear | 10s | Lock/network issues |
| Cloud Services | DecorrelatedJitter | 60s | Respect rate limits |
| Queues | Exponential | 5m | Selective by type |
| Microservices | ExponentialJitter | 10s | Circuit breaker |

<a id="doc-docs-functional-api"></a>

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
use function Cline\Retry\retry;
use Cline\Retry\Strategy\ExponentialBackoff;

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
    return $attempt * 1_000_000; // 1s, 2s, 3s...
};

$retrier = retry(5, $backoff);
$result = $retrier(fn() => $database->query());
```

## Closure Reusability

The returned closure is reusable and can be passed around:

```php
use function Cline\Retry\retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

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

// Compose with functional utilities
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

## Custom Backoff Callables

```php
// Constant backoff (100ms)
$constant = fn(int $attempt): int => 100_000;

// Linear backoff (1s, 2s, 3s...)
$linear = fn(int $attempt): int => $attempt * 1_000_000;

// Exponential backoff (2^attempt * 100ms)
$exponential = fn(int $attempt): int => (2 ** $attempt) * 100_000;

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

## Timing Notes

- Backoff callable receives 1-indexed attempt number (1, 2, 3...)
- Must return microseconds as integer
- No delay occurs after final failed attempt
- No delay occurs after successful attempt

## Advantages

- **Composable**: Works well in functional pipelines
- **Lightweight**: Simple function calls
- **Flexible**: Custom backoff logic with callables
- **Reusable**: One retrier for multiple operations

## Limitations

The functional API is simpler but has fewer features:

- No conditional retry support (use OOP API for that)
- No max delay caps built-in (implement in custom callable or use OOP API)
- Less discoverable than fluent interface

For conditional retries or max delay caps, use the [OOP API](#doc-docs-oop-api).

<a id="doc-docs-oop-api"></a>

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
