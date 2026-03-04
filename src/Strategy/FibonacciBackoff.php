<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Fibonacci backoff strategy with naturally progressive delays.
 *
 * Implements delays that grow according to the Fibonacci sequence, providing
 * moderate backoff that grows more conservatively than exponential but more
 * aggressively than linear. The Fibonacci pattern (1, 1, 2, 3, 5, 8, 13, 21...)
 * offers a balanced middle ground for retry scenarios where exponential growth
 * is too aggressive but linear is too slow.
 *
 * Example with base=1s: 1s, 1s, 2s, 3s, 5s, 8s, 13s
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class FibonacciBackoff implements BackoffStrategy
{
    /**
     * Create a new Fibonacci backoff strategy.
     *
     * @param int $baseMicroseconds Base delay in microseconds that is multiplied by the
     *                              Fibonacci number for each attempt. This base unit determines
     *                              the scale of delays while maintaining Fibonacci progression.
     */
    public function __construct(
        private int $baseMicroseconds,
    ) {}

    /**
     * Create a Fibonacci backoff strategy with millisecond-based timing.
     *
     * @param  int  $base Base delay in milliseconds
     * @return self New Fibonacci backoff instance
     */
    public static function milliseconds(int $base): self
    {
        return new self($base * 1_000);
    }

    /**
     * Create a Fibonacci backoff strategy with second-based timing.
     *
     * @param  int  $base Base delay in seconds
     * @return self New Fibonacci backoff instance
     */
    public static function seconds(int $base): self
    {
        return new self($base * 1_000_000);
    }

    /**
     * Calculate delay using Fibonacci sequence for the given attempt.
     *
     * @param  int $attempt Current retry attempt number starting from 1
     * @return int Calculated delay in microseconds
     */
    public function calculate(int $attempt): int
    {
        return $this->baseMicroseconds * $this->fibonacci($attempt);
    }

    /**
     * Calculate the nth Fibonacci number iteratively.
     *
     * Uses iterative approach for O(n) time complexity and O(1) space complexity.
     * Returns 1 for n <= 1 to ensure minimum delay value for early attempts.
     *
     * @param  int $n Position in Fibonacci sequence
     * @return int Fibonacci number at position n
     */
    private function fibonacci(int $n): int
    {
        if ($n <= 1) {
            return 1;
        }

        $prev = 1;
        $curr = 1;

        for ($i = 2; $i <= $n; ++$i) {
            $next = $prev + $curr;
            $prev = $curr;
            $curr = $next;
        }

        return $curr;
    }
}
