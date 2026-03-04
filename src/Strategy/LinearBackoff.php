<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Linear backoff strategy with arithmetic progression.
 *
 * Implements delays that increase by a constant amount with each attempt,
 * following the pattern base * attempt. This conservative backoff strategy
 * is suitable for scenarios where predictable, gradual delay increases are
 * preferred over aggressive exponential growth. Works well when the underlying
 * issue is likely to resolve quickly.
 *
 * Example with base=1s: 1s, 2s, 3s, 4s, 5s
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class LinearBackoff implements BackoffStrategy
{
    /**
     * Create a new linear backoff strategy.
     *
     * @param int $baseMicroseconds Base delay increment in microseconds. This value is
     *                              multiplied by the attempt number to produce linearly
     *                              increasing delays (base, 2*base, 3*base, etc.).
     */
    public function __construct(
        private int $baseMicroseconds,
    ) {}

    /**
     * Create a linear backoff strategy with millisecond-based timing.
     *
     * @param  int  $base Base delay increment in milliseconds
     * @return self New linear backoff instance
     */
    public static function milliseconds(int $base): self
    {
        return new self($base * 1_000);
    }

    /**
     * Create a linear backoff strategy with second-based timing.
     *
     * @param  int  $base Base delay increment in seconds
     * @return self New linear backoff instance
     */
    public static function seconds(int $base): self
    {
        return new self($base * 1_000_000);
    }

    /**
     * Calculate linearly increasing delay for the given retry attempt.
     *
     * @param  int $attempt Current retry attempt number starting from 1
     * @return int Calculated delay in microseconds (base * attempt)
     */
    public function calculate(int $attempt): int
    {
        return $this->baseMicroseconds * $attempt;
    }
}
