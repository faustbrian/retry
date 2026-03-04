<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\FibonacciBackoff;

describe('FibonacciBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('calculates fibonacci backoff correctly', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);   // 1000 * 1
            expect($backoff->calculate(2))->toBe(2_000);   // 1000 * 2
            expect($backoff->calculate(3))->toBe(3_000);   // 1000 * 3
            expect($backoff->calculate(4))->toBe(5_000);   // 1000 * 5
            expect($backoff->calculate(5))->toBe(8_000);   // 1000 * 8
            expect($backoff->calculate(6))->toBe(13_000);  // 1000 * 13
            expect($backoff->calculate(7))->toBe(21_000);  // 1000 * 21
            expect($backoff->calculate(8))->toBe(34_000);  // 1000 * 34
        });

        test('verifies complete fibonacci sequence accuracy', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1);
            $expectedSequence = [1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377, 610];

            // Act & Assert
            foreach ($expectedSequence as $attempt => $expectedFib) {
                expect($backoff->calculate($attempt))->toBe($expectedFib)
                    ->and($backoff->calculate($attempt))->toBe($expectedFib, sprintf('Attempt %s should return Fibonacci number %s', $attempt, $expectedFib));
            }
        });

        test('creates from milliseconds', function (): void {
            // Arrange
            $backoff = FibonacciBackoff::milliseconds(100);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);   // 100ms * 1
            expect($backoff->calculate(2))->toBe(200_000);   // 100ms * 2
            expect($backoff->calculate(4))->toBe(500_000);   // 100ms * 5
            expect($backoff->calculate(6))->toBe(1_300_000); // 100ms * 13
        });

        test('creates from seconds', function (): void {
            // Arrange
            $backoff = FibonacciBackoff::seconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);   // 1s * 1
            expect($backoff->calculate(2))->toBe(2_000_000);   // 1s * 2
            expect($backoff->calculate(4))->toBe(5_000_000);   // 1s * 5
            expect($backoff->calculate(6))->toBe(13_000_000);  // 1s * 13
        });

        test('produces consistent results for same attempt number', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(5))->toBe(8_000);
            expect($backoff->calculate(5))->toBe(8_000);
            expect($backoff->calculate(5))->toBe(8_000);
        });

        test('maintains immutability across calculations', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1_000);

            // Act
            $delay1 = $backoff->calculate(5);
            $delay2 = $backoff->calculate(3);
            $delay3 = $backoff->calculate(5);

            // Assert
            expect($delay1)->toBe(8_000)
                ->and($delay2)->toBe(3_000)
                ->and($delay3)->toBe(8_000)
                ->and($delay1)->toBe($delay3);
        });

        test('factory methods produce equivalent results', function (): void {
            // Arrange
            $fromMicros = new FibonacciBackoff(1_000);
            $fromMillis = FibonacciBackoff::milliseconds(1);

            // Act & Assert - 1 millisecond = 1000 microseconds
            expect($fromMicros->calculate(5))->toBe($fromMillis->calculate(5));

            // Arrange
            $fromMicros2 = new FibonacciBackoff(1_000_000);
            $fromSeconds = FibonacciBackoff::seconds(1);

            // Act & Assert - 1 second = 1,000,000 microseconds
            expect($fromMicros2->calculate(5))->toBe($fromSeconds->calculate(5));
        });
    });

    describe('Sad Paths', function (): void {
        // FibonacciBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles edge case for attempt 0', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(0))->toBe(1_000); // fib(0) = 1
        });

        test('handles edge case for attempt 1', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000); // fib(1) = 1
        });

        test('handles zero base delay', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
            expect($backoff->calculate(10))->toBe(0);
        });

        test('handles negative base delay', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(-1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(-1_000);
            expect($backoff->calculate(5))->toBe(-8_000);
        });

        test('works with very small base delays', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1)
                ->and($backoff->calculate(10))->toBe(89)
                ->and($backoff->calculate(20))->toBe(10_946);
        });

        test('handles large fibonacci numbers', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1);

            // Act
            $delay10 = $backoff->calculate(10); // fib(10) = 89
            $delay15 = $backoff->calculate(15); // fib(15) = 987

            // Assert
            expect($delay10)->toBe(89);
            expect($delay15)->toBe(987);
        });

        test('handles very large attempt numbers without overflow', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(1);

            // Act & Assert - Fibonacci(30) = 1346269
            $delay30 = $backoff->calculate(30);
            expect($delay30)->toBe(1_346_269);

            // Act & Assert - Fibonacci(35) = 14930352
            $delay35 = $backoff->calculate(35);
            expect($delay35)->toBe(14_930_352);
        });

        test('calculates higher fibonacci numbers efficiently', function (): void {
            // Arrange
            $backoff = new FibonacciBackoff(100);

            // Act
            $delay = $backoff->calculate(20);

            // Assert
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThan(0);
        });
    });
});
