<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\DecorrelatedJitterBackoff;

describe('DecorrelatedJitterBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('calculates decorrelated jitter backoff within valid range', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act & Assert - First attempt should be between base and base * 3
            $delay = $backoff->calculate(1);
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(1_000);
            expect($delay)->toBeLessThanOrEqual(10_000);

            // Act & Assert - Subsequent attempts should also be within range
            for ($i = 2; $i <= 5; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(1_000);
                expect($delay)->toBeLessThanOrEqual(10_000);
            }
        });

        test('respects maximum delay cap', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 5_000);

            // Act & Assert - Even after many attempts, delay should never exceed max
            for ($i = 1; $i <= 20; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeLessThanOrEqual(5_000);
            }
        });

        test('maintains state between calls', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 100_000);

            // Act
            $firstDelay = $backoff->calculate(1);
            $secondDelay = $backoff->calculate(2);

            // Assert
            expect($firstDelay)->toBeInt();
            expect($secondDelay)->toBeInt();
            expect($firstDelay)->toBeGreaterThanOrEqual(1_000);
            expect($secondDelay)->toBeGreaterThanOrEqual(1_000);
        });

        test('produces different values on repeated calls due to randomness', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 100_000);

            // Act
            $values = [];

            for ($i = 0; $i < 20; ++$i) {
                $values[] = $backoff->calculate(1);
            }

            // Assert
            $uniqueValues = array_unique($values);
            expect(count($uniqueValues))->toBeGreaterThan(1);
        });

        test('creates from milliseconds', function (): void {
            // Arrange
            $backoff = DecorrelatedJitterBackoff::milliseconds(100, 1_000);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(100_000);
            expect($delay)->toBeLessThanOrEqual(1_000_000);

            for ($i = 2; $i <= 5; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(100_000);
                expect($delay)->toBeLessThanOrEqual(1_000_000);
            }
        });

        test('creates from seconds', function (): void {
            // Arrange
            $backoff = DecorrelatedJitterBackoff::seconds(1, 10);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(1_000_000);
            expect($delay)->toBeLessThanOrEqual(10_000_000);

            for ($i = 2; $i <= 5; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(1_000_000);
                expect($delay)->toBeLessThanOrEqual(10_000_000);
            }
        });

        test('initializes with base as previous delay', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act - First call uses base as previous delay
            $delay = $backoff->calculate(1);

            // Assert
            expect($delay)->toBeGreaterThanOrEqual(1_000);
            expect($delay)->toBeLessThanOrEqual(3_000); // base * 3
        });

        test('follows decorrelated jitter formula using previous delay', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 100_000);

            // Act & Assert - First call: random_int(base, base * 3)
            $firstDelay = $backoff->calculate(1);
            expect($firstDelay)->toBeGreaterThanOrEqual(1_000);
            expect($firstDelay)->toBeLessThanOrEqual(3_000);

            // Act & Assert - Second call should use first delay as previous
            $secondDelay = $backoff->calculate(2);
            expect($secondDelay)->toBeGreaterThanOrEqual(1_000);
            expect($secondDelay)->toBeLessThanOrEqual(100_000);
        });

        test('does not share state between different instances', function (): void {
            // Arrange
            $backoff1 = new DecorrelatedJitterBackoff(1_000, 10_000);
            $backoff2 = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act
            $delay1 = $backoff1->calculate(1);
            $delay2 = $backoff2->calculate(1);

            // Assert - Both should start from base, so ranges should be identical
            expect($delay1)->toBeGreaterThanOrEqual(1_000);
            expect($delay1)->toBeLessThanOrEqual(3_000);
            expect($delay2)->toBeGreaterThanOrEqual(1_000);
            expect($delay2)->toBeLessThanOrEqual(3_000);

            // Act - Advance first instance
            $backoff1->calculate(2);
            $backoff1->calculate(3);

            // Act & Assert - Second instance should still produce consistent ranges
            $delay2Next = $backoff2->calculate(2);
            expect($delay2Next)->toBeGreaterThanOrEqual(1_000);
        });

        test('handles rapid growth when previous delay is small', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(100, 100_000);

            // Act & Assert
            $previousDelay = 100;

            for ($i = 1; $i <= 10; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(100);
                expect($delay)->toBeLessThanOrEqual(100_000);

                $previousDelay = $delay;
            }
        });

        test('converges to max when previous delay grows large', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act & Assert - After several iterations with max cap, should stay at max
            for ($i = 1; $i <= 30; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeLessThanOrEqual(10_000);
            }

            // Act
            $delays = [];

            for ($i = 1; $i <= 10; ++$i) {
                $delays[] = $backoff->calculate($i);
            }

            // Assert - At least some should be at or near max
            $atOrNearMax = array_filter($delays, fn ($d): bool => $d >= 9_000);
            expect(count($atOrNearMax))->toBeGreaterThan(0);
        });

        test('always returns at least the base delay on first call', function (): void {
            // Arrange
            $base = 5_000;
            $backoff = new DecorrelatedJitterBackoff($base, 50_000);

            // Act & Assert - First call should never be less than base
            for ($i = 0; $i < 100; ++$i) {
                $freshBackoff = new DecorrelatedJitterBackoff($base, 50_000);
                $firstDelay = $freshBackoff->calculate(1);
                expect($firstDelay)->toBeGreaterThanOrEqual($base);
            }
        });

        test('demonstrates AWS decorrelated jitter behavior', function (): void {
            // Arrange - Based on AWS architecture blog post
            $backoff = new DecorrelatedJitterBackoff(100, 20_000);

            // Act
            $delays = [];

            for ($i = 1; $i <= 15; ++$i) {
                $delays[] = $backoff->calculate($i);
            }

            // Assert
            expect($delays)->toHaveCount(15);

            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(100);
                expect($delay)->toBeLessThanOrEqual(20_000);
            }

            // Assert - Should have variation (not monotonic)
            $differences = [];
            $counter = count($delays);

            for ($i = 1; $i < $counter; ++$i) {
                $differences[] = $delays[$i] - $delays[$i - 1];
            }

            $uniqueDiffs = array_unique($differences);
            expect(count($uniqueDiffs))->toBeGreaterThan(1);
        });

        test('handles microsecond precision correctly', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1, 10);

            // Act
            $delay = $backoff->calculate(1);

            // Assert
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(1);
            expect($delay)->toBeLessThanOrEqual(10);
        });

        test('maintains consistency with factory method conversions', function (): void {
            // Arrange - 100ms = 100,000 microseconds
            $backoffFromMs = DecorrelatedJitterBackoff::milliseconds(100, 1_000);
            $backoffDirect = new DecorrelatedJitterBackoff(100_000, 1_000_000);

            // Act & Assert - Both should produce same ranges
            for ($i = 1; $i <= 5; ++$i) {
                $delayFromMs = $backoffFromMs->calculate($i);
                $delayDirect = $backoffDirect->calculate($i);

                expect($delayFromMs)->toBeGreaterThanOrEqual(100_000);
                expect($delayFromMs)->toBeLessThanOrEqual(1_000_000);
                expect($delayDirect)->toBeGreaterThanOrEqual(100_000);
                expect($delayDirect)->toBeLessThanOrEqual(1_000_000);
            }
        });

        test('produces statistically distributed values over many iterations', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 100_000);

            // Act
            $delays = [];

            for ($i = 1; $i <= 50; ++$i) {
                $delays[] = $backoff->calculate($i);
            }

            // Assert
            $uniqueDelays = array_unique($delays);
            expect(count($uniqueDelays))->toBeGreaterThan(10);

            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(1_000);
                expect($delay)->toBeLessThanOrEqual(100_000);
            }
        });
    });

    describe('Sad Paths', function (): void {
        // DecorrelatedJitterBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles minimum base equals maximum scenario', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(5_000, 5_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(5_000);
            expect($backoff->calculate(2))->toBe(5_000);
            expect($backoff->calculate(5))->toBe(5_000);
        });

        test('handles edge case with very small base', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1, 100);

            // Act & Assert
            for ($i = 1; $i <= 10; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(1);
                expect($delay)->toBeLessThanOrEqual(100);
            }
        });

        test('handles edge case with large maximum', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 1_000_000);

            // Act & Assert
            for ($i = 1; $i <= 20; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(1_000);
                expect($delay)->toBeLessThanOrEqual(1_000_000);
            }
        });

        test('handles attempt number being irrelevant to calculation', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act - The attempt number is passed but not used
            $delay1 = $backoff->calculate(1);
            $delay2 = $backoff->calculate(100);
            $delay3 = $backoff->calculate(1);

            // Assert
            expect($delay1)->toBeInt();
            expect($delay2)->toBeInt();
            expect($delay3)->toBeInt();

            expect($delay1)->toBeGreaterThanOrEqual(1_000);
            expect($delay2)->toBeGreaterThanOrEqual(1_000);
            expect($delay3)->toBeGreaterThanOrEqual(1_000);
        });

        test('handles base close to max correctly', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(9_000, 10_000);

            // Act & Assert - First call: random(9000, 27000) capped at 10000
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(9_000);
            expect($delay)->toBeLessThanOrEqual(10_000);

            // Act & Assert - Subsequent calls should also stay in narrow range
            for ($i = 2; $i <= 10; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeGreaterThanOrEqual(9_000);
                expect($delay)->toBeLessThanOrEqual(10_000);
            }
        });

        test('handles very large microsecond values', function (): void {
            // Arrange - 1 hour in microseconds
            $oneHour = 3_600 * 1_000_000;
            $backoff = new DecorrelatedJitterBackoff(1_000_000, $oneHour);

            // Act
            $delay = $backoff->calculate(1);

            // Assert
            expect($delay)->toBeGreaterThanOrEqual(1_000_000);
            expect($delay)->toBeLessThanOrEqual($oneHour);
        });

        test('respects max cap even with aggressive growth', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1, 100);

            // Act & Assert - With base=1, can grow to 3, 9, 27, 81... but capped at 100
            for ($i = 1; $i <= 100; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeLessThanOrEqual(100);
            }
        });

        test('demonstrates independence from attempt count in formula', function (): void {
            // Arrange
            $backoff1 = new DecorrelatedJitterBackoff(1_000, 50_000);
            $backoff2 = new DecorrelatedJitterBackoff(1_000, 50_000);

            // Act
            $delay1a = $backoff1->calculate(1);
            $delay2a = $backoff2->calculate(99);

            // Assert - Both should have similar range (base to base*3)
            expect($delay1a)->toBeGreaterThanOrEqual(1_000);
            expect($delay1a)->toBeLessThanOrEqual(3_000);
            expect($delay2a)->toBeGreaterThanOrEqual(1_000);
            expect($delay2a)->toBeLessThanOrEqual(3_000);
        });

        test('handles single microsecond base and max', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1, 1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1);
            expect($backoff->calculate(2))->toBe(1);
            expect($backoff->calculate(10))->toBe(1);
        });

        test('handles zero attempt number gracefully', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act
            $delay = $backoff->calculate(0);

            // Assert
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(1_000);
        });

        test('handles negative attempt number gracefully', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 10_000);

            // Act
            $delay = $backoff->calculate(-1);

            // Assert
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(1_000);
        });

        test('produces consistent type (always int)', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1, 1_000_000);

            // Act & Assert
            for ($i = 1; $i <= 20; ++$i) {
                $delay = $backoff->calculate($i);
                expect($delay)->toBeInt();
                expect(is_int($delay))->toBeTrue();
                expect(is_float($delay))->toBeFalse();
            }
        });
    });

    describe('Stateful Behavior', function (): void {
        test('remembers previous delay across multiple calls', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 100_000);

            // Act
            $delays = [];

            for ($i = 1; $i <= 10; ++$i) {
                $delays[] = $backoff->calculate($i);
            }

            // Assert
            foreach ($delays as $delay) {
                expect($delay)->toBeGreaterThanOrEqual(1_000);
                expect($delay)->toBeLessThanOrEqual(100_000);
            }

            expect(count($delays))->toBe(10);
        });

        test('uses previous delay to calculate next range', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(1_000, 1_000_000);

            // Act
            $first = $backoff->calculate(1);

            // Assert - First call establishes initial state
            expect($first)->toBeGreaterThanOrEqual(1_000);
            expect($first)->toBeLessThanOrEqual(3_000);

            // Act
            $second = $backoff->calculate(2);

            // Assert - Second call's range depends on first result
            expect($second)->toBeGreaterThanOrEqual(1_000);
            expect($second)->toBeLessThanOrEqual(1_000_000);
        });

        test('maintains distinct state per instance', function (): void {
            // Arrange
            $backoff1 = new DecorrelatedJitterBackoff(1_000, 100_000);
            $backoff2 = new DecorrelatedJitterBackoff(1_000, 100_000);
            $backoff3 = new DecorrelatedJitterBackoff(1_000, 100_000);

            // Act - Advance each instance differently
            $backoff1->calculate(1);

            $backoff2->calculate(1);
            $backoff2->calculate(2);

            $backoff3->calculate(1);
            $backoff3->calculate(2);
            $backoff3->calculate(3);

            // Act
            $delay1 = $backoff1->calculate(2);
            $delay2 = $backoff2->calculate(3);
            $delay3 = $backoff3->calculate(4);

            // Assert
            expect($delay1)->toBeGreaterThanOrEqual(1_000);
            expect($delay2)->toBeGreaterThanOrEqual(1_000);
            expect($delay3)->toBeGreaterThanOrEqual(1_000);
        });

        test('does not reset state between calls', function (): void {
            // Arrange
            $backoff = new DecorrelatedJitterBackoff(100, 10_000);

            // Act
            $delays = [];

            for ($i = 1; $i <= 20; ++$i) {
                $delays[] = $backoff->calculate($i);
            }

            // Assert - If state was resetting, delays would always be in [base, base*3]
            $delaysAboveThreeTimesBase = array_filter($delays, fn ($d): bool => $d > 300);
            expect(count($delaysAboveThreeTimesBase))->toBeGreaterThan(0);
        });
    });
});
