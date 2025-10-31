<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Retry\Strategy;

/**
 * Contract for calculating retry delay backoff timing.
 *
 * Defines the interface for implementing various backoff strategies that determine
 * how long to wait between retry attempts. Implementations include exponential,
 * linear, constant, Fibonacci, and jitter-based approaches to handle transient
 * failures with appropriate spacing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface BackoffStrategy
{
    /**
     * Calculate the delay in microseconds for a given retry attempt.
     *
     * Implementations should return progressively longer delays based on the
     * attempt number to provide appropriate spacing between retries. The delay
     * calculation strategy depends on the implementation (exponential, linear, etc.).
     *
     * @param  int $attempt Current retry attempt number, starting from 1
     * @return int Delay in microseconds to wait before the next retry attempt
     */
    public function calculate(int $attempt): int;
}
