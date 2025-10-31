<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

use function random_int;

/**
 * Exponential backoff strategy with full jitter randomization.
 *
 * Combines exponential backoff with random jitter by selecting a random delay
 * between 0 and the exponential value. This "full jitter" approach helps prevent
 * thundering herd problems where multiple clients retry simultaneously. The
 * randomization spreads retry attempts across time, reducing load spikes on
 * recovering services.
 *
 * Delay calculation: random(0, base * multiplier^(attempt-1))
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ExponentialJitterBackoff implements BackoffStrategy
{
    /**
     * Create a new exponential jitter backoff strategy.
     *
     * @param int   $baseMicroseconds Base delay in microseconds used to calculate the upper
     *                                bound for randomization. This value is multiplied
     *                                exponentially for subsequent attempts.
     * @param float $multiplier       Growth factor applied exponentially for each retry attempt.
     *                                Default is 2.0 (doubling). Determines how aggressively
     *                                the maximum delay grows with each attempt.
     */
    public function __construct(
        private int $baseMicroseconds,
        private float $multiplier = 2.0,
    ) {}

    /**
     * Create an exponential jitter backoff strategy with millisecond-based timing.
     *
     * @param  int   $base       Base delay in milliseconds
     * @param  float $multiplier Growth factor (default: 2.0)
     * @return self  New exponential jitter backoff instance
     */
    public static function milliseconds(int $base, float $multiplier = 2.0): self
    {
        return new self($base * 1_000, $multiplier);
    }

    /**
     * Create an exponential jitter backoff strategy with second-based timing.
     *
     * @param  int   $base       Base delay in seconds
     * @param  float $multiplier Growth factor (default: 2.0)
     * @return self  New exponential jitter backoff instance
     */
    public static function seconds(int $base, float $multiplier = 2.0): self
    {
        return new self($base * 1_000_000, $multiplier);
    }

    /**
     * Calculate randomized delay with exponential upper bound.
     *
     * Computes exponential growth then returns a random value between 0 and that
     * maximum. This full jitter approach provides optimal load distribution when
     * multiple clients are retrying concurrently.
     *
     * @param  int $attempt Current retry attempt number starting from 1
     * @return int Random delay in microseconds between 0 and exponential maximum
     */
    public function calculate(int $attempt): int
    {
        $exponential = (int) ($this->baseMicroseconds * ($this->multiplier ** ($attempt - 1)));

        return random_int(0, $exponential);
    }
}
