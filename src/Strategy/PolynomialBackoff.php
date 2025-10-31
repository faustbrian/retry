<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Implements a polynomial backoff strategy for retry delays.
 *
 * Calculates retry delays using polynomial growth based on the attempt
 * number raised to a configurable degree. With degree=2 (default), this
 * produces quadratic backoff (1x, 4x, 9x, 16x base delay). Higher degrees
 * create more aggressive backoff curves, while degree=1 produces linear
 * growth. Useful for scenarios requiring faster backoff growth than
 * exponential strategies in early attempts.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class PolynomialBackoff implements BackoffStrategy
{
    /**
     * Create a new polynomial backoff strategy instance.
     *
     * @param int $baseMicroseconds The base delay value in microseconds. This value
     *                              is multiplied by the attempt number raised to the
     *                              specified degree to calculate the actual delay.
     * @param int $degree           The polynomial degree (exponent) applied to the
     *                              attempt number. Defaults to 2 for quadratic growth.
     *                              Higher values create more aggressive backoff curves.
     */
    public function __construct(
        private int $baseMicroseconds,
        private int $degree = 2,
    ) {}

    /**
     * Create a polynomial backoff strategy with base delay in milliseconds.
     *
     * @param  int  $base   The base delay value in milliseconds
     * @param  int  $degree The polynomial degree (default: 2 for quadratic)
     * @return self A configured polynomial backoff instance with millisecond-based timing
     */
    public static function milliseconds(int $base, int $degree = 2): self
    {
        return new self($base * 1_000, $degree);
    }

    /**
     * Create a polynomial backoff strategy with base delay in seconds.
     *
     * @param  int  $base   The base delay value in seconds
     * @param  int  $degree The polynomial degree (default: 2 for quadratic)
     * @return self A configured polynomial backoff instance with second-based timing
     */
    public static function seconds(int $base, int $degree = 2): self
    {
        return new self($base * 1_000_000, $degree);
    }

    /**
     * Calculate the polynomial backoff delay for the given attempt.
     *
     * Applies the formula: base * (attempt ^ degree) to determine the delay.
     * For example, with base=100ms and degree=2, attempt 3 yields 900ms
     * (100 * 3^2). The result is cast to int to ensure microsecond precision.
     *
     * @param  int $attempt The attempt number (1-indexed) for which to calculate the delay
     * @return int The calculated delay in microseconds based on polynomial growth
     */
    public function calculate(int $attempt): int
    {
        return (int) ($this->baseMicroseconds * ($attempt ** $this->degree));
    }
}
