<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Exponential backoff strategy with configurable growth multiplier.
 *
 * Implements exponential delay growth where each retry attempt waits progressively
 * longer than the previous attempt. The delay is calculated as: base * multiplier^(attempt-1).
 * This aggressive backoff pattern is ideal for transient failures that may require
 * increasingly longer recovery times, such as database connection issues or overloaded
 * external APIs.
 *
 * Example with base=1s and multiplier=2: 1s, 2s, 4s, 8s, 16s
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ExponentialBackoff implements BackoffStrategy
{
    /**
     * Create a new exponential backoff strategy.
     *
     * @param int   $baseMicroseconds Base delay in microseconds before the first retry.
     *                                This value is multiplied exponentially for subsequent
     *                                attempts based on the multiplier.
     * @param float $multiplier       Growth factor applied exponentially for each retry attempt.
     *                                Default is 2.0 (doubling). Values greater than 1 produce
     *                                increasing delays; exactly 1 produces constant delays.
     */
    public function __construct(
        private int $baseMicroseconds,
        private float $multiplier = 2.0,
    ) {}

    /**
     * Create an exponential backoff strategy with millisecond-based timing.
     *
     * @param  int   $base       Base delay in milliseconds
     * @param  float $multiplier Growth factor (default: 2.0)
     * @return self  New exponential backoff instance
     */
    public static function milliseconds(int $base, float $multiplier = 2.0): self
    {
        return new self($base * 1_000, $multiplier);
    }

    /**
     * Create an exponential backoff strategy with second-based timing.
     *
     * @param  int   $base       Base delay in seconds
     * @param  float $multiplier Growth factor (default: 2.0)
     * @return self  New exponential backoff instance
     */
    public static function seconds(int $base, float $multiplier = 2.0): self
    {
        return new self($base * 1_000_000, $multiplier);
    }

    /**
     * Calculate exponentially growing delay for the given retry attempt.
     *
     * @param  int $attempt Current retry attempt number starting from 1
     * @return int Calculated delay in microseconds
     */
    public function calculate(int $attempt): int
    {
        return (int) ($this->baseMicroseconds * ($this->multiplier ** ($attempt - 1)));
    }
}
