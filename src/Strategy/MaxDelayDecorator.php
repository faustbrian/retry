<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

use function min;

/**
 * Caps the delay returned by a backoff strategy to a maximum value.
 *
 * This decorator wraps any BackoffStrategy and ensures the calculated
 * delay never exceeds a specified maximum. Useful for preventing
 * unbounded exponential or polynomial backoffs from creating
 * excessively long wait times in production systems.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class MaxDelayDecorator implements BackoffStrategy
{
    /**
     * Create a new maximum delay decorator instance.
     *
     * @param BackoffStrategy $strategy        The underlying backoff strategy to decorate.
     *                                         This strategy's calculated delays will be capped
     *                                         to the maximum value specified.
     * @param int             $maxMicroseconds The maximum delay allowed in microseconds.
     *                                         Any delay exceeding this value will be reduced
     *                                         to this maximum, preventing unbounded wait times.
     */
    public function __construct(
        private BackoffStrategy $strategy,
        private int $maxMicroseconds,
    ) {}

    /**
     * Create a decorator with maximum delay specified in milliseconds.
     *
     * @param  BackoffStrategy $strategy The backoff strategy to decorate
     * @param  int             $max      The maximum delay in milliseconds
     * @return self            A configured decorator instance with millisecond-based maximum
     */
    public static function milliseconds(BackoffStrategy $strategy, int $max): self
    {
        return new self($strategy, $max * 1_000);
    }

    /**
     * Create a decorator with maximum delay specified in seconds.
     *
     * @param  BackoffStrategy $strategy The backoff strategy to decorate
     * @param  int             $max      The maximum delay in seconds
     * @return self            A configured decorator instance with second-based maximum
     */
    public static function seconds(BackoffStrategy $strategy, int $max): self
    {
        return new self($strategy, $max * 1_000_000);
    }

    /**
     * Calculate the backoff delay for the given attempt, capped at the maximum.
     *
     * Delegates to the wrapped strategy to calculate the delay, then ensures
     * the result does not exceed the configured maximum value. This provides
     * a safety mechanism against unbounded backoff growth.
     *
     * @param  int $attempt The attempt number (1-indexed) for which to calculate the delay
     * @return int The calculated delay in microseconds, capped at the configured maximum
     */
    public function calculate(int $attempt): int
    {
        return min($this->strategy->calculate($attempt), $this->maxMicroseconds);
    }
}
