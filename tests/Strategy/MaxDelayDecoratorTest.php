<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\ConstantBackoff;
use Cline\Retry\Strategy\ExponentialBackoff;
use Cline\Retry\Strategy\FibonacciBackoff;
use Cline\Retry\Strategy\LinearBackoff;
use Cline\Retry\Strategy\MaxDelayDecorator;
use Cline\Retry\Strategy\PolynomialBackoff;

describe('MaxDelayDecorator', function (): void {
    describe('Happy Path - Delay Capping', function (): void {
        test('caps delays that exceed maximum with ExponentialBackoff', function (): void {
            $strategy = new ExponentialBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, 5_000);

            expect($decorator->calculate(1))->toBe(1_000);  // 1000 < 5000, unchanged
            expect($decorator->calculate(2))->toBe(2_000);  // 2000 < 5000, unchanged
            expect($decorator->calculate(3))->toBe(4_000);  // 4000 < 5000, unchanged
            expect($decorator->calculate(4))->toBe(5_000);  // 8000 > 5000, capped
            expect($decorator->calculate(5))->toBe(5_000);  // 16000 > 5000, capped
            expect($decorator->calculate(6))->toBe(5_000);  // 32000 > 5000, capped
        });

        test('caps delays that exceed maximum with LinearBackoff', function (): void {
            $strategy = new LinearBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, 3_500);

            expect($decorator->calculate(1))->toBe(1_000);  // 1000 < 3500
            expect($decorator->calculate(2))->toBe(2_000);  // 2000 < 3500
            expect($decorator->calculate(3))->toBe(3_000);  // 3000 < 3500
            expect($decorator->calculate(4))->toBe(3_500);  // 4000 > 3500, capped
            expect($decorator->calculate(5))->toBe(3_500);  // 5000 > 3500, capped
        });

        test('caps delays that exceed maximum with PolynomialBackoff', function (): void {
            $strategy = new PolynomialBackoff(1_000, 2);
            $decorator = new MaxDelayDecorator($strategy, 10_000);

            expect($decorator->calculate(1))->toBe(1_000);   // 1000 < 10000
            expect($decorator->calculate(2))->toBe(4_000);   // 4000 < 10000
            expect($decorator->calculate(3))->toBe(9_000);   // 9000 < 10000
            expect($decorator->calculate(4))->toBe(10_000); // 16000 > 10000, capped
            expect($decorator->calculate(5))->toBe(10_000); // 25000 > 10000, capped
        });

        test('caps delays that exceed maximum with FibonacciBackoff', function (): void {
            $strategy = new FibonacciBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, 10_000);

            expect($decorator->calculate(1))->toBe(1_000);   // 1000 * 1 = 1000 < 10000
            expect($decorator->calculate(2))->toBe(2_000);   // 1000 * 2 = 2000 < 10000
            expect($decorator->calculate(3))->toBe(3_000);   // 1000 * 3 = 3000 < 10000
            expect($decorator->calculate(4))->toBe(5_000);   // 1000 * 5 = 5000 < 10000
            expect($decorator->calculate(5))->toBe(8_000);   // 1000 * 8 = 8000 < 10000
            expect($decorator->calculate(6))->toBe(10_000); // 1000 * 13 = 13000 > 10000, capped
            expect($decorator->calculate(7))->toBe(10_000); // 1000 * 21 = 21000 > 10000, capped
        });

        test('preserves all delays when none exceed maximum', function (): void {
            $strategy = new ConstantBackoff(3_000);
            $decorator = new MaxDelayDecorator($strategy, 10_000);

            expect($decorator->calculate(1))->toBe(3_000);
            expect($decorator->calculate(5))->toBe(3_000);
            expect($decorator->calculate(10))->toBe(3_000);
            expect($decorator->calculate(100))->toBe(3_000);
        });

        test('preserves exponential growth pattern below maximum', function (): void {
            $strategy = new ExponentialBackoff(100, 3.0);
            $decorator = new MaxDelayDecorator($strategy, 100_000);

            // With multiplier 3.0: 100, 300, 900, 2700, 8100, 24300...
            expect($decorator->calculate(1))->toBe(100);
            expect($decorator->calculate(2))->toBe(300);
            expect($decorator->calculate(3))->toBe(900);
            expect($decorator->calculate(4))->toBe(2_700);
            expect($decorator->calculate(5))->toBe(8_100);
        });

        test('works correctly at exact boundary condition', function (): void {
            $strategy = new LinearBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, 3_000);

            expect($decorator->calculate(2))->toBe(2_000);  // 2000 < 3000
            expect($decorator->calculate(3))->toBe(3_000);  // 3000 = 3000, exact match
            expect($decorator->calculate(4))->toBe(3_000);  // 4000 > 3000, capped
        });
    });

    describe('Happy Path - Factory Methods', function (): void {
        test('creates from milliseconds factory method', function (): void {
            $strategy = ExponentialBackoff::milliseconds(100);
            $decorator = MaxDelayDecorator::milliseconds($strategy, 500);

            expect($decorator->calculate(1))->toBe(100_000);  // 100ms < 500ms
            expect($decorator->calculate(2))->toBe(200_000);  // 200ms < 500ms
            expect($decorator->calculate(3))->toBe(400_000);  // 400ms < 500ms
            expect($decorator->calculate(4))->toBe(500_000);  // 800ms > 500ms, capped
            expect($decorator->calculate(5))->toBe(500_000);  // 1600ms > 500ms, capped
        });

        test('creates from seconds factory method', function (): void {
            $strategy = ExponentialBackoff::seconds(1);
            $decorator = MaxDelayDecorator::seconds($strategy, 5);

            expect($decorator->calculate(1))->toBe(1_000_000);  // 1s < 5s
            expect($decorator->calculate(2))->toBe(2_000_000);  // 2s < 5s
            expect($decorator->calculate(3))->toBe(4_000_000);  // 4s < 5s
            expect($decorator->calculate(4))->toBe(5_000_000);  // 8s > 5s, capped
            expect($decorator->calculate(5))->toBe(5_000_000);  // 16s > 5s, capped
        });

        test('converts milliseconds to microseconds correctly', function (): void {
            $strategy = new ConstantBackoff(250_000); // 250ms in microseconds
            $decorator = MaxDelayDecorator::milliseconds($strategy, 200);

            // Max is 200ms = 200_000 microseconds
            expect($decorator->calculate(1))->toBe(200_000);
        });

        test('converts seconds to microseconds correctly', function (): void {
            $strategy = new ConstantBackoff(3_000_000); // 3s in microseconds
            $decorator = MaxDelayDecorator::seconds($strategy, 2);

            // Max is 2s = 2_000_000 microseconds
            expect($decorator->calculate(1))->toBe(2_000_000);
        });
    });

    describe('Sad Path - Invalid Scenarios', function (): void {
        test('caps all delays when all exceed maximum', function (): void {
            $strategy = new LinearBackoff(10_000);
            $decorator = new MaxDelayDecorator($strategy, 5_000);

            // All attempts exceed max, should all be capped
            expect($decorator->calculate(1))->toBe(5_000);  // 10000 > 5000
            expect($decorator->calculate(2))->toBe(5_000);  // 20000 > 5000
            expect($decorator->calculate(3))->toBe(5_000);  // 30000 > 5000
            expect($decorator->calculate(10))->toBe(5_000); // 100000 > 5000
        });

        test('handles strategy returning zero delay', function (): void {
            $strategy = new ConstantBackoff(0);
            $decorator = new MaxDelayDecorator($strategy, 5_000);

            expect($decorator->calculate(1))->toBe(0);
            expect($decorator->calculate(5))->toBe(0);
            expect($decorator->calculate(100))->toBe(0);
        });

        test('handles zero maximum delay', function (): void {
            $strategy = new LinearBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, 0);

            // All delays should be capped to 0
            expect($decorator->calculate(1))->toBe(0);
            expect($decorator->calculate(5))->toBe(0);
            expect($decorator->calculate(10))->toBe(0);
        });

        test('handles extremely high delays from wrapped strategy', function (): void {
            $strategy = new ExponentialBackoff(1_000_000, 10.0);
            $decorator = new MaxDelayDecorator($strategy, 5_000_000);

            // Even with aggressive exponential growth, cap is enforced
            $result = $decorator->calculate(10);
            expect($result)->toBe(5_000_000);
            expect($result)->toBeLessThanOrEqual(5_000_000);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles very small maximum delays', function (): void {
            $strategy = new LinearBackoff(100);
            $decorator = new MaxDelayDecorator($strategy, 1);

            expect($decorator->calculate(1))->toBe(1);
            expect($decorator->calculate(5))->toBe(1);
        });

        test('handles very large maximum delays', function (): void {
            $strategy = new ExponentialBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, \PHP_INT_MAX);

            // With extremely large max, strategy behavior is preserved
            expect($decorator->calculate(1))->toBe(1_000);
            expect($decorator->calculate(2))->toBe(2_000);
            expect($decorator->calculate(3))->toBe(4_000);
        });

        test('handles first attempt when delay already exceeds maximum', function (): void {
            $strategy = new ConstantBackoff(10_000);
            $decorator = new MaxDelayDecorator($strategy, 5_000);

            // Even first attempt exceeds max
            expect($decorator->calculate(1))->toBe(5_000);
        });

        test('can be nested with multiple decorators', function (): void {
            $strategy = new ExponentialBackoff(1_000);
            $decorator1 = new MaxDelayDecorator($strategy, 10_000);
            $decorator2 = new MaxDelayDecorator($decorator1, 5_000);

            // The outer decorator should apply the stricter limit
            expect($decorator2->calculate(1))->toBe(1_000);
            expect($decorator2->calculate(2))->toBe(2_000);
            expect($decorator2->calculate(3))->toBe(4_000);
            expect($decorator2->calculate(4))->toBe(5_000);  // Capped by outer decorator
            expect($decorator2->calculate(5))->toBe(5_000);
        });

        test('can be nested with looser outer limit', function (): void {
            $strategy = new ExponentialBackoff(1_000);
            $decorator1 = new MaxDelayDecorator($strategy, 5_000);
            $decorator2 = new MaxDelayDecorator($decorator1, 10_000);

            // Inner decorator applies stricter limit
            expect($decorator2->calculate(1))->toBe(1_000);
            expect($decorator2->calculate(2))->toBe(2_000);
            expect($decorator2->calculate(3))->toBe(4_000);
            expect($decorator2->calculate(4))->toBe(5_000);  // Capped by inner decorator
            expect($decorator2->calculate(5))->toBe(5_000);
        });

        test('handles single microsecond precision', function (): void {
            $strategy = new ConstantBackoff(1);
            $decorator = new MaxDelayDecorator($strategy, 1);

            expect($decorator->calculate(1))->toBe(1);
            expect($decorator->calculate(100))->toBe(1);
        });

        test('maintains consistency across multiple calls with same attempt', function (): void {
            $strategy = new LinearBackoff(1_000);
            $decorator = new MaxDelayDecorator($strategy, 5_000);

            // Same attempt number should always return same result
            expect($decorator->calculate(10))->toBe(5_000);
            expect($decorator->calculate(10))->toBe(5_000);
            expect($decorator->calculate(10))->toBe(5_000);
        });

        test('handles transition point where capping begins', function (): void {
            $strategy = new ExponentialBackoff(1_000, 2.0);
            $decorator = new MaxDelayDecorator($strategy, 4_500);

            expect($decorator->calculate(1))->toBe(1_000);  // 1000 < 4500
            expect($decorator->calculate(2))->toBe(2_000);  // 2000 < 4500
            expect($decorator->calculate(3))->toBe(4_000);  // 4000 < 4500 (just under)
            expect($decorator->calculate(4))->toBe(4_500);  // 8000 > 4500 (capped)
        });

        test('handles polynomial backoff with high degree', function (): void {
            $strategy = new PolynomialBackoff(100, 4);
            $decorator = new MaxDelayDecorator($strategy, 50_000);

            expect($decorator->calculate(1))->toBe(100);    // 100 * 1^4 = 100
            expect($decorator->calculate(2))->toBe(1_600);   // 100 * 2^4 = 1600
            expect($decorator->calculate(3))->toBe(8_100);   // 100 * 3^4 = 8100
            expect($decorator->calculate(4))->toBe(25_600);  // 100 * 4^4 = 25600
            expect($decorator->calculate(5))->toBe(50_000); // 100 * 5^4 = 62500 > 50000, capped
        });
    });

    describe('Integration - Real World Scenarios', function (): void {
        test('prevents retry storms with reasonable cap on exponential backoff', function (): void {
            // Common pattern: exponential backoff with max delay to prevent excessive waits
            $strategy = ExponentialBackoff::seconds(1);
            $decorator = MaxDelayDecorator::seconds($strategy, 60); // Max 1 minute

            // First few attempts grow exponentially
            expect($decorator->calculate(1))->toBe(1_000_000);   // 1s
            expect($decorator->calculate(2))->toBe(2_000_000);   // 2s
            expect($decorator->calculate(3))->toBe(4_000_000);   // 4s
            expect($decorator->calculate(4))->toBe(8_000_000);   // 8s
            expect($decorator->calculate(5))->toBe(16_000_000);  // 16s
            expect($decorator->calculate(6))->toBe(32_000_000);  // 32s

            // After this, delays would exceed 60s, so they're capped
            expect($decorator->calculate(7))->toBe(60_000_000);  // 64s > 60s, capped
            expect($decorator->calculate(10))->toBe(60_000_000); // Still capped
        });

        test('provides predictable max latency for API retries', function (): void {
            // API calls with 5 second timeout, exponential backoff capped at 5s
            $strategy = ExponentialBackoff::milliseconds(100);
            $decorator = MaxDelayDecorator::milliseconds($strategy, 5_000);

            // Quick initial retries
            expect($decorator->calculate(1))->toBe(100_000);   // 100ms
            expect($decorator->calculate(2))->toBe(200_000);   // 200ms
            expect($decorator->calculate(3))->toBe(400_000);   // 400ms
            expect($decorator->calculate(4))->toBe(800_000);   // 800ms
            expect($decorator->calculate(5))->toBe(1_600_000); // 1.6s
            expect($decorator->calculate(6))->toBe(3_200_000); // 3.2s

            // Then capped at 5s
            expect($decorator->calculate(7))->toBe(5_000_000);  // 6.4s > 5s, capped
            expect($decorator->calculate(10))->toBe(5_000_000); // Always capped
        });

        test('limits database retry delays in high-throughput systems', function (): void {
            // Linear backoff for database contention, max 100ms
            $strategy = LinearBackoff::milliseconds(10);
            $decorator = MaxDelayDecorator::milliseconds($strategy, 100);

            expect($decorator->calculate(1))->toBe(10_000);   // 10ms
            expect($decorator->calculate(5))->toBe(50_000);   // 50ms
            expect($decorator->calculate(10))->toBe(100_000); // 100ms (capped)
            expect($decorator->calculate(20))->toBe(100_000); // Still capped
        });

        test('provides gradual backoff with ceiling for user-facing operations', function (): void {
            // Fibonacci backoff capped at 10 seconds for better UX
            $strategy = FibonacciBackoff::milliseconds(500);
            $decorator = MaxDelayDecorator::seconds($strategy, 10);

            expect($decorator->calculate(1))->toBe(500_000);    // 0.5s
            expect($decorator->calculate(2))->toBe(1_000_000);  // 1s
            expect($decorator->calculate(3))->toBe(1_500_000);  // 1.5s
            expect($decorator->calculate(4))->toBe(2_500_000);  // 2.5s
            expect($decorator->calculate(5))->toBe(4_000_000);  // 4s
            expect($decorator->calculate(6))->toBe(6_500_000);  // 6.5s
            expect($decorator->calculate(7))->toBe(10_000_000); // 10.5s > 10s, capped
        });

        test('combines with custom multiplier for aggressive capping', function (): void {
            // Fast-growing exponential with aggressive cap
            $strategy = new ExponentialBackoff(1_000, 5.0);
            $decorator = new MaxDelayDecorator($strategy, 30_000);

            expect($decorator->calculate(1))->toBe(1_000);   // 1000 * 5^0 = 1000
            expect($decorator->calculate(2))->toBe(5_000);   // 1000 * 5^1 = 5000
            expect($decorator->calculate(3))->toBe(25_000);  // 1000 * 5^2 = 25000
            expect($decorator->calculate(4))->toBe(30_000); // 1000 * 5^3 = 125000 > 30000, capped
        });
    });
});
