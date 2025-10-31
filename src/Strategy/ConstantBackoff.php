<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Constant backoff strategy with fixed delay between retries.
 *
 * Provides consistent, unchanging delay between all retry attempts. Suitable
 * for scenarios where predictable, evenly-spaced retries are preferred, such
 * as polling external services with rate limits or when exponential growth
 * would be too aggressive.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ConstantBackoff implements BackoffStrategy
{
    /**
     * Create a new constant backoff strategy.
     *
     * @param int $delayMicroseconds Fixed delay in microseconds to apply between all retry
     *                               attempts. This delay remains constant regardless of the
     *                               attempt number, providing predictable retry timing.
     */
    public function __construct(
        private int $delayMicroseconds,
    ) {}

    /**
     * Create a constant backoff strategy with millisecond-based delay.
     *
     * @param  int  $delay Fixed delay in milliseconds between retry attempts
     * @return self New constant backoff instance
     */
    public static function milliseconds(int $delay): self
    {
        return new self($delay * 1_000);
    }

    /**
     * Create a constant backoff strategy with second-based delay.
     *
     * @param  int  $delay Fixed delay in seconds between retry attempts
     * @return self New constant backoff instance
     */
    public static function seconds(int $delay): self
    {
        return new self($delay * 1_000_000);
    }

    /**
     * Calculate the constant delay for the given retry attempt.
     *
     * @param  int $attempt Current retry attempt number (unused in constant strategy)
     * @return int Fixed delay in microseconds
     */
    public function calculate(int $attempt): int
    {
        return $this->delayMicroseconds;
    }
}
