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
