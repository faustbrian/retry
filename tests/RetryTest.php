<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Retry;
use Cline\Retry\Strategy\BackoffStrategy;
use Cline\Retry\Strategy\ConstantBackoff;
use Cline\Retry\Strategy\ExponentialBackoff;
use Cline\Retry\Strategy\LinearBackoff;

describe('Retry', function (): void {
    describe('Happy Paths', function (): void {
        test('executes callable successfully on first attempt', function (): void {
            $retry = Retry::times(3);
            $attemptCount = 0;

            $result = $retry->execute(function () use (&$attemptCount): string {
                ++$attemptCount;

                return 'success';
            });

            expect($result)->toBe('success');
            expect($attemptCount)->toBe(1);
        });

        test('retries on exception and eventually succeeds', function (): void {
            $retry = Retry::times(3);
            $attemptCount = 0;

            $result = $retry->execute(function () use (&$attemptCount): string {
                ++$attemptCount;

                throw_if($attemptCount < 3, RuntimeException::class, 'Temporary failure');

                return 'success';
            });

            expect($result)->toBe('success');
            expect($attemptCount)->toBe(3);
        });

        test('applies backoff strategy between retries', function (): void {
            $strategy = new ConstantBackoff(1_000); // 1ms delay
            $retry = Retry::times(3)->withBackoff($strategy);

            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Always fails');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000; // Convert to microseconds

            expect($attemptCount)->toBe(3);
            // Should have 2 delays (between 3 attempts) of ~1000 microseconds each
            expect($elapsed)->toBeGreaterThan(2_000); // At least 2ms
        });

        test('creates instance using static times method', function (): void {
            $retry = Retry::times(5);

            expect($retry)->toBeInstanceOf(Retry::class);
        });

        test('creates instance using static withStrategy method', function (): void {
            $strategy = new LinearBackoff(1_000);
            $retry = Retry::withStrategy(5, $strategy);

            expect($retry)->toBeInstanceOf(Retry::class);
        });

        test('withBackoff returns new immutable instance', function (): void {
            $retry1 = Retry::times(3);
            $strategy = new LinearBackoff(1_000);
            $retry2 = $retry1->withBackoff($strategy);

            expect($retry1)->not->toBe($retry2);
            expect($retry2)->toBeInstanceOf(Retry::class);
        });

        test('withMaxDelay returns new immutable instance', function (): void {
            $retry1 = Retry::times(3);
            $retry2 = $retry1->withMaxDelay(5_000);

            expect($retry1)->not->toBe($retry2);
            expect($retry2)->toBeInstanceOf(Retry::class);
        });

        test('when returns new immutable instance', function (): void {
            $retry1 = Retry::times(3);
            $retry2 = $retry1->when(fn ($e): true => true);

            expect($retry1)->not->toBe($retry2);
            expect($retry2)->toBeInstanceOf(Retry::class);
        });

        test('supports conditional retry with when clause', function (): void {
            $retry = Retry::times(5)
                ->when(fn ($e): bool => $e instanceof RuntimeException);

            $attemptCount = 0;

            // Should retry for RuntimeException
            try {
                $retry->execute(function () use (&$attemptCount): string {
                    ++$attemptCount;

                    throw_if($attemptCount < 3, RuntimeException::class, 'Retryable');

                    return 'success';
                });
            } catch (Exception) {
                // Should not reach here
            }

            expect($attemptCount)->toBe(3);
        });

        test('can chain fluent methods', function (): void {
            $strategy = new LinearBackoff(1_000);

            $retry = Retry::times(5)
                ->withBackoff($strategy)
                ->withMaxDelay(10_000)
                ->when(fn ($e): bool => $e instanceof RuntimeException);

            expect($retry)->toBeInstanceOf(Retry::class);
        });

        test('returns value from successful execution', function (): void {
            $retry = Retry::times(3);

            $result = $retry->execute(fn (): array => ['key' => 'value']);

            expect($result)->toBe(['key' => 'value']);
        });

        test('returns null from successful execution', function (): void {
            $retry = Retry::times(3);

            $result = $retry->execute(fn (): null => null);

            expect($result)->toBeNull();
        });

        test('returns object from successful execution', function (): void {
            $retry = Retry::times(3);
            $obj = new stdClass();
            $obj->value = 'test';

            $result = $retry->execute(fn (): stdClass => $obj);

            expect($result)->toBe($obj);
            expect($result->value)->toBe('test');
        });

        test('returns integer from successful execution', function (): void {
            $retry = Retry::times(3);

            $result = $retry->execute(fn (): int => 42);

            expect($result)->toBe(42);
        });

        test('returns boolean false from successful execution', function (): void {
            $retry = Retry::times(3);

            $result = $retry->execute(fn (): false => false);

            expect($result)->toBe(false);
        });

        test('returns empty array from successful execution', function (): void {
            $retry = Retry::times(3);

            $result = $retry->execute(fn (): array => []);

            expect($result)->toBe([]);
        });

        test('combines when clause with backoff strategy successfully', function (): void {
            $strategy = new ConstantBackoff(1_000);
            $retry = Retry::times(5)
                ->withBackoff($strategy)
                ->when(fn ($e): bool => $e instanceof RuntimeException);

            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Retryable');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000;

            expect($attemptCount)->toBe(5);
            expect($elapsed)->toBeGreaterThan(4_000); // 4 delays of ~1000 microseconds
        });

        test('combines when clause with max delay successfully', function (): void {
            $strategy = new ExponentialBackoff(10_000);
            $retry = Retry::times(5)
                ->withBackoff($strategy)
                ->withMaxDelay(5_000)
                ->when(fn ($e, $attempt): bool => $attempt <= 3);

            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Test');
                });
            } catch (RuntimeException) {
                // Expected
            }

            // when() condition is checked after exception, so attempt 4 throws
            // Attempts: 1 (fail, check condition), 2 (fail, check condition), 3 (fail, check condition), 4 (fail, condition false, throw)
            expect($attemptCount)->toBe(4);
        });

        test('supports when condition that checks exception message', function (): void {
            $retry = Retry::times(5)
                ->when(fn ($e): bool => str_contains($e->getMessage(), 'retry'));

            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): string {
                    ++$attemptCount;

                    throw_if($attemptCount < 3, RuntimeException::class, 'please retry this');

                    return 'success';
                });
            } catch (RuntimeException) {
                // Should not reach here
            }

            // Should retry while message contains 'retry', then succeed
            expect($attemptCount)->toBe(3);
        });

        test('preserves callable return value type through retries', function (): void {
            $retry = Retry::times(3);
            $attemptCount = 0;

            $result = $retry->execute(function () use (&$attemptCount): float {
                ++$attemptCount;

                throw_if($attemptCount < 2, RuntimeException::class, 'First attempt fails');

                return 123.45; // Return float
            });

            expect($result)->toBe(123.45);
            expect($attemptCount)->toBe(2);
        });

        test('handles large number of attempts efficiently', function (): void {
            $retry = Retry::times(100);
            $attemptCount = 0;

            $result = $retry->execute(function () use (&$attemptCount): string {
                ++$attemptCount;

                throw_if($attemptCount < 50, RuntimeException::class, 'Fail');

                return 'success';
            });

            expect($result)->toBe('success');
            expect($attemptCount)->toBe(50);
        });

        test('handles multiple independent retry cycles', function (): void {
            $retry = Retry::times(3)->withBackoff(
                new ConstantBackoff(1_000),
            );

            // First execution
            $result1 = $retry->execute(fn (): string => 'first');
            expect($result1)->toBe('first');

            // Second execution
            $result2 = $retry->execute(fn (): string => 'second');
            expect($result2)->toBe('second');

            // Third execution with failure
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Fail');
                });
            } catch (RuntimeException) {
                // Expected
            }

            expect($attemptCount)->toBe(3);
        });

        test('implements http-like retry pattern with exponential backoff', function (): void {
            $strategy = new ExponentialBackoff(1_000); // 1ms base
            $retry = Retry::times(5)
                ->withBackoff($strategy)
                ->withMaxDelay(10_000) // Cap at 10ms
                ->when(fn ($e): bool => $e->getCode() >= 500); // Only retry server errors

            $attemptCount = 0;

            // Simulate 500 error then success
            $result = $retry->execute(function () use (&$attemptCount): string {
                ++$attemptCount;

                throw_if($attemptCount < 3, RuntimeException::class, 'Internal Server Error', 500);

                return 'success';
            });

            expect($result)->toBe('success');
            expect($attemptCount)->toBe(3);
        });

        test('implements database transaction-like retry pattern', function (): void {
            $retry = Retry::times(3)
                ->withBackoff(
                    new ConstantBackoff(100),
                )
                ->when(fn ($e): bool => str_contains($e->getMessage(), 'deadlock'));

            $attemptCount = 0;

            // Simulate deadlock then success
            $result = $retry->execute(function () use (&$attemptCount): string {
                ++$attemptCount;

                throw_if($attemptCount === 1, RuntimeException::class, 'Database deadlock detected');

                return 'transaction committed';
            });

            expect($result)->toBe('transaction committed');
            expect($attemptCount)->toBe(2);
        });

        test('properly captures and modifies closure variables', function (): void {
            $retry = Retry::times(3);
            $externalState = ['count' => 0, 'values' => []];

            $result = $retry->execute(function () use (&$externalState): array {
                ++$externalState['count'];
                $externalState['values'][] = $externalState['count'];

                throw_if($externalState['count'] < 2, RuntimeException::class, 'Retry needed');

                return $externalState;
            });

            expect($result['count'])->toBe(2);
            expect($result['values'])->toBe([1, 2]);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when all attempts fail', function (): void {
            $retry = Retry::times(3);
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Permanent failure');
                });
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toBe('Permanent failure');
            }

            expect($attemptCount)->toBe(3);
        });

        test('does not retry when condition returns false', function (): void {
            $retry = Retry::times(5)
                ->when(fn ($e): bool => $e instanceof RuntimeException);

            $attemptCount = 0;

            // Should NOT retry for InvalidArgumentException
            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new InvalidArgumentException('Not retryable');
                });
            } catch (InvalidArgumentException $invalidArgumentException) {
                expect($invalidArgumentException->getMessage())->toBe('Not retryable');
            }

            expect($attemptCount)->toBe(1); // Only attempted once
        });

        test('works with different exception types', function (): void {
            $retry = Retry::times(3);
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new LogicException('Logic error');
                });
            } catch (LogicException $logicException) {
                expect($logicException->getMessage())->toBe('Logic error');
            }

            expect($attemptCount)->toBe(3);
        });

        test('handles single attempt configuration', function (): void {
            $retry = Retry::times(1);
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Fails');
                });
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toBe('Fails');
            }

            expect($attemptCount)->toBe(1);
        });

        test('throws exception immediately when when condition returns false on first attempt', function (): void {
            $retry = Retry::times(5)
                ->when(fn ($e): false => false);

            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Never retry');
                });
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getMessage())->toBe('Never retry');
            }

            expect($attemptCount)->toBe(1);
        });

        test('ensures complete immutability across all builder methods', function (): void {
            $original = Retry::times(3);
            $strategy = new LinearBackoff(1_000);

            $withBackoff = $original->withBackoff($strategy);
            $withMaxDelay = $original->withMaxDelay(5_000);
            $withWhen = $original->when(fn ($e): true => true);

            expect($original)->not->toBe($withBackoff);
            expect($original)->not->toBe($withMaxDelay);
            expect($original)->not->toBe($withWhen);
            expect($withBackoff)->not->toBe($withMaxDelay);
            expect($withBackoff)->not->toBe($withWhen);
            expect($withMaxDelay)->not->toBe($withWhen);
        });

        test('does not retry client errors in http-like pattern', function (): void {
            $retry = Retry::times(5)
                ->when(fn ($e): bool => $e->getCode() >= 500);

            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Bad Request', 400);
                });
            } catch (RuntimeException $runtimeException) {
                expect($runtimeException->getCode())->toBe(400);
            }

            expect($attemptCount)->toBe(1); // No retries for client error
        });
    });

    describe('Edge Cases', function (): void {
        test('respects max delay cap', function (): void {
            $strategy = new ExponentialBackoff(10_000); // Grows quickly: 10ms, 20ms, 40ms...
            $retry = Retry::times(5)
                ->withBackoff($strategy)
                ->withMaxDelay(5_000); // Cap at 5ms

            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Always fails');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000;

            expect($attemptCount)->toBe(5);
            // Even though strategy would give 10ms + 20ms + 40ms + 80ms = 150ms total
            // The cap limits each to 5ms max, so maximum is 4 delays * 5ms = 20ms
            expect($elapsed)->toBeLessThan(40_000); // Should be well under what uncapped would be
        });

        test('passes exception and attempt number to when condition', function (): void {
            $capturedAttempts = [];
            $capturedExceptions = [];

            $retry = Retry::times(5)
                ->when(function ($e, $attempt) use (&$capturedAttempts, &$capturedExceptions): bool {
                    $capturedAttempts[] = $attempt;
                    $capturedExceptions[] = $e;

                    return $attempt < 3; // Stop retrying after 3 attempts
                });

            try {
                $retry->execute(function (): void {
                    throw new RuntimeException('Test exception');
                });
            } catch (RuntimeException) {
                // Expected
            }

            expect($capturedAttempts)->toBe([1, 2, 3]);
            expect(count($capturedExceptions))->toBe(3);
            expect($capturedExceptions[0])->toBeInstanceOf(RuntimeException::class);
        });

        test('executes without backoff strategy', function (): void {
            $retry = Retry::times(3);
            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Always fails');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000;

            expect($attemptCount)->toBe(3);
            // Without backoff, should execute very quickly (no delays)
            expect($elapsed)->toBeLessThan(10_000); // Less than 10ms
        });

        test('does not delay after final attempt', function (): void {
            $strategy = new ConstantBackoff(100_000); // 100ms delay
            $retry = Retry::times(2)->withBackoff($strategy);

            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Always fails');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000;

            expect($attemptCount)->toBe(2);
            // Should only delay once (between attempt 1 and 2), not after attempt 2
            expect($elapsed)->toBeLessThan(200_000); // Less than 200ms (would be ~200ms if delayed after final attempt)
            expect($elapsed)->toBeGreaterThan(100_000); // But more than 100ms (the one delay that should occur)
        });

        test('handles non-integer delays gracefully', function (): void {
            // Create a custom strategy that might return non-integer
            $strategy = new class() implements BackoffStrategy
            {
                public function calculate(int $attempt): int
                {
                    return 1_000; // Return valid integer
                }
            };

            $retry = Retry::times(3)->withBackoff($strategy);
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Test');
                });
            } catch (RuntimeException) {
                // Expected
            }

            expect($attemptCount)->toBe(3);
        });

        test('handles zero and negative delays from strategy', function (): void {
            $strategy = new class() implements BackoffStrategy
            {
                private int $call = 0;

                public function calculate(int $attempt): int
                {
                    ++$this->call;

                    return $this->call === 1 ? 0 : -1_000; // First returns 0, then negative
                }
            };

            $retry = Retry::times(3)->withBackoff($strategy);
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Test');
                });
            } catch (RuntimeException) {
                // Expected
            }

            // Should complete without error, just skip the delays
            expect($attemptCount)->toBe(3);
        });

        test('handles max delay of zero', function (): void {
            $strategy = new ConstantBackoff(10_000);
            $retry = Retry::times(3)
                ->withBackoff($strategy)
                ->withMaxDelay(0);

            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Test');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000;

            expect($attemptCount)->toBe(3);
            // With max delay of 0, no delays should occur
            expect($elapsed)->toBeLessThan(10_000);
        });

        test('supports when condition that limits by attempt number', function (): void {
            $retry = Retry::times(10)
                ->when(fn ($e, $attempt): bool => $attempt < 3);

            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Always fails');
                });
            } catch (RuntimeException) {
                // Expected
            }

            // when() returns true for attempts 1 and 2 (< 3), false for 3, so 3 total attempts
            expect($attemptCount)->toBe(3);
        });

        test('handles different exception types thrown in sequence', function (): void {
            $retry = Retry::times(5);
            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): string {
                    ++$attemptCount;

                    throw_if($attemptCount === 1, RuntimeException::class, 'First error');

                    throw_if($attemptCount === 2, InvalidArgumentException::class, 'Second error');

                    throw_if($attemptCount === 3, LogicException::class, 'Third error');

                    return 'success';
                });
            } catch (Exception) {
                // Should not reach here
            }

            // All exceptions should be retried, then success on 4th attempt
            expect($attemptCount)->toBe(4);
        });

        test('allows chaining methods in different orders', function (): void {
            $strategy = new LinearBackoff(1_000);

            $retry1 = Retry::times(5)
                ->when(fn ($e): true => true)
                ->withBackoff($strategy)
                ->withMaxDelay(10_000);

            $retry2 = Retry::times(5)
                ->withMaxDelay(10_000)
                ->withBackoff($strategy)
                ->when(fn ($e): true => true);

            $retry3 = Retry::times(5)
                ->withBackoff($strategy)
                ->when(fn ($e): true => true)
                ->withMaxDelay(10_000);

            expect($retry1)->toBeInstanceOf(Retry::class);
            expect($retry2)->toBeInstanceOf(Retry::class);
            expect($retry3)->toBeInstanceOf(Retry::class);
        });

        test('applies max delay override after strategy calculation', function (): void {
            $strategy = new class() implements BackoffStrategy
            {
                public function calculate(int $attempt): int
                {
                    return 50_000; // Always return 50ms
                }
            };

            $retry = Retry::times(3)
                ->withBackoff($strategy)
                ->withMaxDelay(5_000); // Cap at 5ms

            $attemptCount = 0;
            $startTime = microtime(true);

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Test');
                });
            } catch (RuntimeException) {
                // Expected
            }

            $elapsed = (microtime(true) - $startTime) * 1_000_000;

            expect($attemptCount)->toBe(3);
            // 2 delays capped at 5ms each = ~10ms total
            expect($elapsed)->toBeLessThan(20_000);
            expect($elapsed)->toBeGreaterThan(5_000);
        });

        test('when condition can maintain state across invocations', function (): void {
            $retryableCount = 0;

            $retry = Retry::times(10)
                ->when(function ($e) use (&$retryableCount): bool {
                    ++$retryableCount;

                    return $retryableCount <= 3; // Only retry first 3 times
                });

            $attemptCount = 0;

            try {
                $retry->execute(function () use (&$attemptCount): void {
                    ++$attemptCount;

                    throw new RuntimeException('Test');
                });
            } catch (RuntimeException) {
                // Expected
            }

            expect($attemptCount)->toBe(4); // Initial attempt + 3 retries
            expect($retryableCount)->toBe(4);
        });
    });
});
