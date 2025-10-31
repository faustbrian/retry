<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

use function min;
use function random_int;

/**
 * Decorrelated jitter backoff strategy with randomized, state-dependent delays.
 *
 * Implements AWS's recommended decorrelated jitter algorithm that uses the previous
 * delay to calculate the next delay with randomization. This approach provides better
 * behavior than exponential jitter by preventing synchronized retries across multiple
 * clients while maintaining aggressive backoff. Particularly effective for distributed
 * systems to avoid thundering herd problems.
 *
 * The delay calculation uses: random(base, min(max, previousDelay * 3))
 *
 * @see https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DecorrelatedJitterBackoff implements BackoffStrategy
{
    /**
     * Previous delay value used for calculating next delay.
     */
    private int $previousDelay;

    /**
     * Create a new decorrelated jitter backoff strategy.
     *
     * @param int $baseMicroseconds Base delay in microseconds used as the minimum delay
     *                              and initial previous delay value. This establishes
     *                              the lower bound for all retry delays.
     * @param int $maxMicroseconds  Maximum delay ceiling in microseconds to cap retry delays.
     *                              Prevents delays from growing unbounded and ensures
     *                              reasonable retry timing even after many attempts.
     */
    public function __construct(
        private readonly int $baseMicroseconds,
        private readonly int $maxMicroseconds,
    ) {
        $this->previousDelay = $this->baseMicroseconds;
    }

    /**
     * Create a decorrelated jitter backoff strategy with millisecond-based timing.
     *
     * @param  int  $base Base delay in milliseconds
     * @param  int  $max  Maximum delay ceiling in milliseconds
     * @return self New decorrelated jitter backoff instance
     */
    public static function milliseconds(int $base, int $max): self
    {
        return new self($base * 1_000, $max * 1_000);
    }

    /**
     * Create a decorrelated jitter backoff strategy with second-based timing.
     *
     * @param  int  $base Base delay in seconds
     * @param  int  $max  Maximum delay ceiling in seconds
     * @return self New decorrelated jitter backoff instance
     */
    public static function seconds(int $base, int $max): self
    {
        return new self($base * 1_000_000, $max * 1_000_000);
    }

    /**
     * Calculate the next delay using decorrelated jitter algorithm.
     *
     * Generates a random delay between the base and 3x the previous delay, capped
     * at the maximum. This state-dependent randomization decorrelates retry attempts
     * across multiple clients, preventing synchronized retry storms.
     *
     * @param  int $attempt Current retry attempt number (unused in this strategy)
     * @return int Calculated delay in microseconds
     */
    public function calculate(int $attempt): int
    {
        $temp = random_int($this->baseMicroseconds, $this->previousDelay * 3);
        $this->previousDelay = min($this->maxMicroseconds, $temp);

        return $this->previousDelay;
    }
}
