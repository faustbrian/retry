# Backoff Strategies

This library provides 8 different backoff strategies to handle various retry scenarios. Each strategy calculates delays differently to suit specific use cases.

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
