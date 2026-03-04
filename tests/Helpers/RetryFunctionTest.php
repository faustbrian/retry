<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\ConstantBackoff;
use Cline\Retry\Strategy\ExponentialBackoff;
use Cline\Retry\Strategy\LinearBackoff;

use function Cline\Retry\retry;

describe('retry', function (): void {
    test('executes callable successfully on first attempt', function (): void {
        $attemptCount = 0;

        $result = retry(3)(function () use (&$attemptCount): string {
            ++$attemptCount;

            return 'success';
        });

        expect($result)->toBe('success');
        expect($attemptCount)->toBe(1);
    });

    test('retries on exception and eventually succeeds', function (): void {
        $attemptCount = 0;

        $result = retry(3)(function () use (&$attemptCount): string {
            ++$attemptCount;

            throw_if($attemptCount < 3, RuntimeException::class, 'Temporary failure');

            return 'success';
        });

        expect($result)->toBe('success');
        expect($attemptCount)->toBe(3);
    });

    test('throws exception when all attempts fail', function (): void {
        $attemptCount = 0;

        try {
            retry(3)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Permanent failure');
            });
        } catch (RuntimeException $runtimeException) {
            expect($runtimeException->getMessage())->toBe('Permanent failure');
        }

        expect($attemptCount)->toBe(3);
    });

    test('works without backoff strategy', function (): void {
        $attemptCount = 0;
        $startTime = microtime(true);

        try {
            retry(3)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $elapsed = (microtime(true) - $startTime) * 1_000_000;

        expect($attemptCount)->toBe(3);
        // Without backoff, should execute very quickly
        expect($elapsed)->toBeLessThan(10_000); // Less than 10ms
    });

    test('applies BackoffStrategy instance', function (): void {
        $strategy = new ConstantBackoff(1_000); // 1ms delay
        $attemptCount = 0;
        $startTime = microtime(true);

        try {
            retry(3, $strategy)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $elapsed = (microtime(true) - $startTime) * 1_000_000;

        expect($attemptCount)->toBe(3);
        // Should have 2 delays of ~1000 microseconds each
        expect($elapsed)->toBeGreaterThan(2_000);
    });

    test('applies callable backoff strategy', function (): void {
        $backoffCalls = [];
        $backoff = function (int $attempt) use (&$backoffCalls): int {
            $backoffCalls[] = $attempt;

            return $attempt * 1_000; // Linear backoff
        };

        $attemptCount = 0;
        $startTime = microtime(true);

        try {
            retry(3, $backoff)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $elapsed = (microtime(true) - $startTime) * 1_000_000;

        expect($attemptCount)->toBe(3);
        expect($backoffCalls)->toBe([1, 2]); // Called for attempts 1 and 2 (not after final attempt)
        expect($elapsed)->toBeGreaterThan(3_000); // 1ms + 2ms = 3ms minimum
    });

    test('returns closure that can be reused', function (): void {
        $retryFn = retry(3);
        $count1 = 0;
        $count2 = 0;

        $result1 = $retryFn(function () use (&$count1): string {
            ++$count1;

            return 'first';
        });

        $result2 = $retryFn(function () use (&$count2): string {
            ++$count2;

            return 'second';
        });

        expect($result1)->toBe('first');
        expect($result2)->toBe('second');
        expect($count1)->toBe(1);
        expect($count2)->toBe(1);
    });

    test('can be used with different strategies', function (): void {
        $attemptCount = 0;

        retry(3, new LinearBackoff(500))(function () use (&$attemptCount): string {
            ++$attemptCount;

            throw_if($attemptCount < 2, RuntimeException::class, 'Fail');

            return 'success';
        });

        expect($attemptCount)->toBe(2);
    });

    test('handles zero delays from callable', function (): void {
        $backoff = fn (int $attempt): int => 0;
        $attemptCount = 0;

        try {
            retry(3, $backoff)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attemptCount)->toBe(3);
    });

    test('handles negative delays from callable', function (): void {
        $backoff = fn (int $attempt): int => -1_000;
        $attemptCount = 0;

        try {
            retry(3, $backoff)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attemptCount)->toBe(3);
    });

    test('does not call backoff after final attempt', function (): void {
        $backoffCalls = [];
        $backoff = function (int $attempt) use (&$backoffCalls): int {
            $backoffCalls[] = $attempt;

            return 1_000;
        };

        try {
            retry(2, $backoff)(function (): void {
                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        // Should only call backoff once (between attempt 1 and 2)
        expect($backoffCalls)->toBe([1]);
    });

    test('works with exponential backoff strategy', function (): void {
        $strategy = new ExponentialBackoff(1_000);
        $attemptCount = 0;

        try {
            retry(4, $strategy)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attemptCount)->toBe(4);
    });

    test('passes attempt number correctly to callable backoff', function (): void {
        $capturedAttempts = [];
        $backoff = function (int $attempt) use (&$capturedAttempts): int {
            $capturedAttempts[] = $attempt;

            return 100;
        };

        try {
            retry(5, $backoff)(function (): void {
                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        // Backoff called for attempts 1-4 (not after final attempt 5)
        expect($capturedAttempts)->toBe([1, 2, 3, 4]);
    });

    test('handles single attempt', function (): void {
        $attemptCount = 0;

        try {
            retry(1)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Fails');
            });
        } catch (RuntimeException $runtimeException) {
            expect($runtimeException->getMessage())->toBe('Fails');
        }

        expect($attemptCount)->toBe(1);
    });

    test('returns value from successful execution', function (): void {
        $result = retry(3)(fn (): array => ['key' => 'value']);

        expect($result)->toBe(['key' => 'value']);
    });

    test('returns null from successful execution', function (): void {
        $result = retry(3)(fn (): null => null);

        expect($result)->toBeNull();
    });

    test('handles different exception types', function (): void {
        $attemptCount = 0;

        try {
            retry(3)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new LogicException('Logic error');
            });
        } catch (LogicException $logicException) {
            expect($logicException->getMessage())->toBe('Logic error');
        }

        expect($attemptCount)->toBe(3);
    });

    test('can be composed with other functions', function (): void {
        $fetchData = fn () => throw new RuntimeException('Network error');

        $retryableFetch = retry(3, new ConstantBackoff(100));

        expect(fn () => $retryableFetch($fetchData))
            ->toThrow(RuntimeException::class, 'Network error');
    });

    test('handles non-integer return from callable backoff gracefully', function (): void {
        // Return a float that can be cast to int
        $backoff = fn (int $attempt): int => (int) 1_500.5;
        $attemptCount = 0;

        try {
            retry(2, $backoff)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attemptCount)->toBe(2);
    });

    test('supports complex backoff logic in callable', function (): void {
        // Custom backoff: double delay each time, capped at 10ms
        $backoff = function (int $attempt): int {
            $delay = 1_000 * (2 ** ($attempt - 1));

            return min($delay, 10_000);
        };

        $attemptCount = 0;
        $startTime = microtime(true);

        try {
            retry(5, $backoff)(function () use (&$attemptCount): void {
                ++$attemptCount;

                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $elapsed = (microtime(true) - $startTime) * 1_000_000;

        expect($attemptCount)->toBe(5);
        // Delays: 1ms, 2ms, 4ms, 8ms = 15ms minimum
        expect($elapsed)->toBeGreaterThan(15_000);
    });
});
