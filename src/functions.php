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

use function is_int;

/**
 * Create a retry wrapper that executes a callable with automatic retry logic.
 *
 * Returns a higher-order function that wraps any callable with retry
 * behavior. The wrapper attempts execution up to the specified maximum
 * attempts, optionally applying a backoff strategy between failures.
 * If all attempts fail, the last exception is re-thrown.
 *
 * ```php
 * // Retry an API call up to 3 times with exponential backoff
 * $result = retry(3, ExponentialBackoff::seconds(1))(
 *     fn() => $httpClient->get('/api/data')
 * );
 *
 * // Simple retry without backoff
 * $result = retry(5)(fn() => $database->query($sql));
 * ```
 *
 * @param int                           $maxAttempts The maximum number of execution attempts (must be >= 1).
 *                                                   After this many failures, the last exception is thrown.
 * @param null|BackoffStrategy|callable $backoff     Optional backoff strategy or callable that determines
 *                                                   the delay between retry attempts. Can be a BackoffStrategy
 *                                                   instance or a callable receiving the attempt number and
 *                                                   returning delay in microseconds. When null, retries occur
 *                                                   immediately without delay.
 *
 * @throws Throwable The last exception encountered after all retry attempts are exhausted
 *
 * @return Closure A function that accepts a callable and executes it with retry logic.
 *                 The returned closure will re-throw the last exception if all attempts fail.
 */
function retry(int $maxAttempts, BackoffStrategy|callable|null $backoff = null): Closure
{
    return static function (callable $fn) use ($maxAttempts, $backoff): mixed {
        /** @var null|Throwable $lastException */
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                return $fn();
            } catch (Throwable $e) {
                $lastException = $e;

                if ($attempt < $maxAttempts && $backoff !== null) {
                    $microseconds = $backoff instanceof BackoffStrategy
                        ? $backoff->calculate($attempt)
                        : $backoff($attempt);

                    if ($microseconds > 0 && is_int($microseconds)) {
                        Sleep::usleep($microseconds);
                    }
                }
            }
        }

        /** @phpstan-ignore throw.notThrowable */
        throw $lastException;
    };
}
