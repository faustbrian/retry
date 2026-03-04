<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry;

use Cline\Retry\Strategy\BackoffStrategy;
use Closure;
use Illuminate\Support\Sleep;
use Throwable;

use function min;
use function throw_if;

/**
 * Retry mechanism with configurable backoff strategies and custom retry conditions.
 *
 * Provides a fluent API for executing operations with automatic retry logic, supporting
 * various backoff strategies to handle transient failures gracefully. The class is immutable,
 * ensuring thread-safe configuration through method chaining that returns new instances.
 *
 * ```php
 * // Simple retry with 3 attempts
 * $result = Retry::times(3)->execute(fn() => $api->call());
 *
 * // With exponential backoff
 * $result = Retry::times(5)
 *     ->withBackoff(ExponentialBackoff::seconds(1))
 *     ->execute(fn() => $api->call());
 *
 * // With conditional retry
 * $result = Retry::times(3)
 *     ->when(fn($e) => $e instanceof TransientException)
 *     ->execute(fn() => $api->call());
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class Retry
{
    /**
     * Create a new retry instance with configurable behavior.
     *
     * @param int                                $maxAttempts          Maximum number of execution attempts before giving up.
     *                                                                 Must be at least 1. Each attempt includes the initial
     *                                                                 execution plus subsequent retries.
     * @param null|BackoffStrategy               $strategy             Backoff strategy that calculates delay between retry
     *                                                                 attempts. When null, retries occur immediately without
     *                                                                 delay. Strategy determines timing pattern (exponential,
     *                                                                 linear, constant, etc.).
     * @param null|int                           $maxDelayMicroseconds Upper bound for retry delay in microseconds. Caps delays
     *                                                                 calculated by the backoff strategy to prevent excessive
     *                                                                 waiting. When null, no limit is applied to strategy delays.
     * @param null|Closure(Throwable, int): bool $shouldRetry          Custom condition that determines whether to retry
     *                                                                 after an exception. Receives the thrown exception
     *                                                                 and current attempt number. When null, all exceptions
     *                                                                 trigger retries. Return false to abort retries and
     *                                                                 re-throw the exception immediately.
     */
    public function __construct(
        private int $maxAttempts,
        private ?BackoffStrategy $strategy = null,
        private ?int $maxDelayMicroseconds = null,
        private ?Closure $shouldRetry = null,
    ) {}

    /**
     * Create a retry instance with specified number of attempts.
     *
     * @param  int  $attempts Maximum number of execution attempts
     * @return self New retry instance with no backoff strategy
     */
    public static function times(int $attempts): self
    {
        return new self($attempts);
    }

    /**
     * Create a retry instance with a backoff strategy.
     *
     * @param  int             $attempts Maximum number of execution attempts
     * @param  BackoffStrategy $strategy Backoff strategy for calculating retry delays
     * @return self            New retry instance with configured strategy
     */
    public static function withStrategy(int $attempts, BackoffStrategy $strategy): self
    {
        return new self($attempts, $strategy);
    }

    /**
     * Execute a callable with automatic retry logic.
     *
     * Attempts to execute the callable up to the configured maximum attempts. When
     * exceptions occur, applies the backoff strategy (if configured) and optional
     * retry condition to determine whether to retry. Delays are capped by maxDelay
     * if configured.
     *
     * @param callable(): mixed $fn Callable to execute with retry logic. Should throw
     *                              exceptions on failure that can be caught and retried.
     *
     * @throws Throwable The last exception thrown when all retry attempts are exhausted,
     *                   or immediately if shouldRetry condition returns false
     *
     * @return mixed The successful return value from the callable
     */
    public function execute(callable $fn): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
            try {
                return $fn();
            } catch (Throwable $e) {
                throw_if($this->shouldRetry instanceof Closure && !($this->shouldRetry)($e, $attempt), $e);

                $lastException = $e;

                if ($attempt < $this->maxAttempts && $this->strategy instanceof BackoffStrategy) {
                    $microseconds = $this->strategy->calculate($attempt);

                    if ($this->maxDelayMicroseconds !== null) {
                        $microseconds = min($microseconds, $this->maxDelayMicroseconds);
                    }

                    if ($microseconds > 0) {
                        Sleep::usleep($microseconds);
                    }
                }
            }
        }

        throw $lastException;
    }

    /**
     * Create a new instance with a different backoff strategy.
     *
     * @param  BackoffStrategy $strategy Backoff strategy for calculating retry delays
     * @return self            New retry instance with updated strategy
     */
    public function withBackoff(BackoffStrategy $strategy): self
    {
        return new self(
            $this->maxAttempts,
            $strategy,
            $this->maxDelayMicroseconds,
            $this->shouldRetry,
        );
    }

    /**
     * Create a new instance with a maximum delay cap.
     *
     * @param  int  $microseconds Maximum delay in microseconds to cap backoff calculations
     * @return self New retry instance with delay limit applied
     */
    public function withMaxDelay(int $microseconds): self
    {
        return new self(
            $this->maxAttempts,
            $this->strategy,
            $microseconds,
            $this->shouldRetry,
        );
    }

    /**
     * Create a new instance with a custom retry condition.
     *
     * @param  Closure(Throwable, int): bool $condition Function that determines whether to retry
     *                                                  based on the exception and attempt number.
     *                                                  Return true to retry, false to abort.
     * @return self                          New retry instance with custom retry logic
     */
    public function when(Closure $condition): self
    {
        return new self(
            $this->maxAttempts,
            $this->strategy,
            $this->maxDelayMicroseconds,
            $condition,
        );
    }
}
