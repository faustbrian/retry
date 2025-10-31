# Examples

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
            ->when(function ($exception, $attempt) {
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
            ->when(function ($exception) {
                // Retry only on deadlock
                return str_contains($exception->getMessage(), 'Deadlock') ||
                       str_contains($exception->getMessage(), 'Lock wait timeout');
            })
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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

## File Upload with Retry

Upload large files with automatic retry on failure:

```php
use Cline\Retry\Retry;
use Cline\Retry\Strategy\ExponentialJitterBackoff;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class FileUploader
{
    public function __construct(
        private S3Client $s3
    ) {}

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

## External API Integration

Integrate with unreliable third-party API:

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

    private CircuitBreaker $circuitBreaker;
}
```

## Functional Pipeline with Retry

Using retry in functional pipelines:

```php
use function Cline\Retry\retry;
use Cline\Retry\Strategy\ExponentialBackoff;

// Create reusable retrier
$retrier = retry(5, ExponentialBackoff::milliseconds(100));

// Process data pipeline
function processDataFeed(string $url): array
{
    global $retrier;

    return pipe(
        $url,
        $retrier(file_get_contents(...)),  // Retry fetching
        json_decode(...),
        fn($data) => array_filter($data, fn($item) => $item['active']),
        fn($data) => array_map(fn($item) => [
            'id' => $item['id'],
            'name' => $item['name'],
            'processed_at' => time(),
        ], $data)
    );
}

$results = processDataFeed('https://api.example.com/data');
```

## Cache-Aside Pattern with Retry

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

## Common Patterns Summary

1. **HTTP APIs**: ExponentialJitterBackoff with max delay, don't retry 4xx
2. **Databases**: ExponentialBackoff with short delays, retry only deadlocks
3. **File Systems**: LinearBackoff, retry on lock/network issues
4. **Cloud Services**: DecorrelatedJitter (AWS-style), respect rate limits
5. **Queues**: ExponentialBackoff, long max delays, selective retry
6. **Microservices**: Short timeouts, circuit breaker integration
7. **Functional**: Reusable retriers in pipelines
