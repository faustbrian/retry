# Retry Patterns

Common retry patterns for real-world scenarios including APIs, databases, message queues, and distributed systems.

## HTTP API Retry Pattern

### Basic API Retry

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

$response = Retry::times(5)
    ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
    ->withMaxDelay(30_000_000) // 30 seconds
    ->execute(fn() => $http->get('https://api.example.com/data'));
```

### Smart HTTP Retry (Don't Retry 4xx)

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

$response = Retry::times(5)
    ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
    ->when(function ($exception, $attempt) {
        // Don't retry client errors (400-499)
        if ($exception instanceof ClientException) {
            return false;
        }

        // Retry server errors (500-599)
        if ($exception instanceof ServerException) {
            return true;
        }

        // Retry connection errors
        return true;
    })
    ->execute(fn() => $client->request('GET', '/endpoint'));
```

### Rate-Limited API

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ConstantBackoff;

class RateLimitedApi
{
    public function call(string $endpoint): array
    {
        return Retry::times(5)
            ->withBackoff(ConstantBackoff::seconds(60))
            ->when(function ($exception) {
                // Retry on 429 Too Many Requests
                return $exception instanceof RateLimitException ||
                       ($exception->getCode() === 429);
            })
            ->execute(fn() => $this->makeRequest($endpoint));
    }
}
```

## Database Retry Patterns

### Deadlock Retry

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;
use Illuminate\Database\QueryException;

DB::transaction(function () {
    return Retry::times(10)
        ->withBackoff(ExponentialBackoff::milliseconds(50))
        ->withMaxDelay(5_000_000) // 5 seconds
        ->when(fn($e) =>
            $e instanceof QueryException &&
            str_contains($e->getMessage(), 'Deadlock')
        )
        ->execute(function () {
            // Your transactional logic here
            $user = User::find(1);
            $user->balance -= 100;
            $user->save();
        });
});
```

### Connection Pool Exhaustion

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\LinearBackoff;

$result = Retry::times(3)
    ->withBackoff(LinearBackoff::milliseconds(500))
    ->when(fn($e) =>
        str_contains($e->getMessage(), 'Too many connections')
    )
    ->execute(fn() => $database->query($sql));
```

### Lock Wait Timeout

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

$result = Retry::times(5)
    ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
    ->when(fn($e) =>
        str_contains($e->getMessage(), 'Lock wait timeout exceeded')
    )
    ->execute(fn() => $db->update($query));
```

## Message Queue Patterns

### Queue Job with Backoff

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

class ProcessOrder
{
    public function handle(Order $order): void
    {
        Retry::times(5)
            ->withBackoff(ExponentialBackoff::seconds(2))
            ->when(fn($e) => $e instanceof ExternalServiceException)
            ->execute(function () use ($order) {
                $this->externalService->process($order);
            });
    }
}
```

### SQS Message Processing

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\DecorrelatedJitterBackoff;

class SqsMessageProcessor
{
    public function process(Message $message): void
    {
        Retry::times(3)
            ->withBackoff(DecorrelatedJitterBackoff::milliseconds(100, 30_000))
            ->execute(function () use ($message) {
                $this->handleMessage($message);
            });
    }
}
```

## File System Patterns

### File Lock Retry

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\LinearBackoff;

$content = Retry::times(10)
    ->withBackoff(LinearBackoff::milliseconds(100))
    ->when(fn($e) => str_contains($e->getMessage(), 'Resource temporarily unavailable'))
    ->execute(function () use ($file) {
        $fp = fopen($file, 'r');
        flock($fp, LOCK_EX);
        $content = fread($fp, filesize($file));
        flock($fp, LOCK_UN);
        fclose($fp);
        return $content;
    });
```

### Network File System

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use function Cline\Retry\retry;

// Functional style
$retrier = retry(5, ExponentialJitterBackoff::milliseconds(200));

$data = $retrier(fn() => file_get_contents('nfs://server/file.txt'));
```

## Cloud Service Patterns

### AWS S3 Upload

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use Aws\Exception\AwsException;

$result = Retry::times(5)
    ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
    ->withMaxDelay(20_000_000) // 20 seconds
    ->when(function ($exception) {
        if (!$exception instanceof AwsException) {
            return false;
        }

        // Retry on throttling
        if ($exception->getAwsErrorCode() === 'RequestThrottled') {
            return true;
        }

        // Retry on 5xx errors
        return $exception->getStatusCode() >= 500;
    })
    ->execute(fn() => $s3->putObject([
        'Bucket' => 'my-bucket',
        'Key' => 'file.txt',
        'Body' => $content,
    ]));
```

### Redis Connection

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

$redis = Retry::times(3)
    ->withBackoff(ExponentialBackoff::milliseconds(200))
    ->when(fn($e) => $e instanceof RedisException)
    ->execute(fn() => new Redis(['host' => 'redis-server']));
```

## Distributed Systems

### Service Discovery Retry

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;

$service = Retry::times(10)
    ->withBackoff(ExponentialJitterBackoff::milliseconds(100))
    ->when(fn($e) => $e instanceof ServiceUnavailableException)
    ->execute(fn() => $serviceRegistry->discover('payment-service'));
```

### Circuit Breaker Integration

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialBackoff;

class ResilientService
{
    public function call(): mixed
    {
        if ($this->circuitBreaker->isOpen()) {
            throw new CircuitBreakerOpenException();
        }

        return Retry::times(3)
            ->withBackoff(ExponentialBackoff::milliseconds(100))
            ->when(function ($exception, $attempt) {
                // Don't retry if circuit opens
                if ($exception instanceof CircuitBreakerOpenException) {
                    return false;
                }

                // Track failures
                $this->circuitBreaker->recordFailure();

                return true;
            })
            ->execute(fn() => $this->externalService->call());
    }
}
```

## Testing Patterns

### Retry in Integration Tests

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ConstantBackoff;

// Wait for async operation to complete
$result = Retry::times(10)
    ->withBackoff(ConstantBackoff::milliseconds(100))
    ->when(fn($e) => $e instanceof NotFoundException)
    ->execute(fn() => $this->database->find($id));
```

### Flaky Test Stabilization

```php
use function Cline\Retry\retry;
use Cline\Retry\Strategy\LinearBackoff;

test('external API returns data', function () {
    $retrier = retry(3, LinearBackoff::milliseconds(500));

    $data = $retrier(fn() => $this->api->fetchData());

    expect($data)->toBeArray();
});
```

## Anti-Patterns to Avoid

### ❌ Retrying Non-Idempotent Operations

```php
// BAD: Payment processing should not retry
Retry::times(3)->execute(fn() => $this->chargeCard($amount));

// GOOD: Check if already processed
Retry::times(3)->execute(function () use ($orderId, $amount) {
    if ($this->paymentExists($orderId)) {
        return $this->getPayment($orderId);
    }
    return $this->chargeCard($amount);
});
```

### ❌ No Max Delay Cap

```php
// BAD: Exponential backoff without cap
Retry::times(20)
    ->withBackoff(ExponentialBackoff::seconds(1))
    ->execute(fn() => $api->call());
// Could wait 2^19 seconds = 6 days on last attempt!

// GOOD: With reasonable cap
Retry::times(20)
    ->withBackoff(ExponentialBackoff::seconds(1))
    ->withMaxDelay(300_000_000) // 5 minutes max
    ->execute(fn() => $api->call());
```

### ❌ Retrying Everything

```php
// BAD: Retrying all exceptions
Retry::times(5)->execute(fn() => $api->call());

// GOOD: Selective retry
Retry::times(5)
    ->when(fn($e) => $e instanceof TransientException)
    ->execute(fn() => $api->call());
```

### ❌ Constant Backoff for Everything

```php
// BAD: No jitter causes thundering herd
Retry::times(5)
    ->withBackoff(ConstantBackoff::seconds(1))
    ->execute(fn() => $api->call());

// GOOD: Jitter prevents synchronized retries
Retry::times(5)
    ->withBackoff(ExponentialJitterBackoff::seconds(1))
    ->execute(fn() => $api->call());
```

## Best Practices

1. **Always use jitter in production** - Prevents thundering herd
2. **Cap maximum delays** - Prevent unbounded waiting
3. **Retry only transient failures** - Don't retry validation errors
4. **Log retry attempts** - Track retry behavior for debugging
5. **Use idempotency tokens** - Prevent duplicate operations
6. **Set reasonable attempt limits** - Balance between resilience and responsiveness
7. **Monitor retry metrics** - Track success/failure rates
