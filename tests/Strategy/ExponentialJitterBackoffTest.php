<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\ExponentialJitterBackoff;

describe('ExponentialJitterBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('calculates exponential jitter backoff within valid range with default multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000);

            // Act & Assert - Attempt 1: 0 to 1000 (1000 * 2^0)
            $delay = $backoff->calculate(1);
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);

            // Act & Assert - Attempt 2: 0 to 2000 (1000 * 2^1)
            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(2_000);

            // Act & Assert - Attempt 3: 0 to 4000 (1000 * 2^2)
            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(4_000);

            // Act & Assert - Attempt 4: 0 to 8000 (1000 * 2^3)
            $delay = $backoff->calculate(4);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(8_000);

            // Act & Assert - Attempt 5: 0 to 16000 (1000 * 2^4)
            $delay = $backoff->calculate(5);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(16_000);
        });

        test('calculates exponential jitter backoff within valid range with custom multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000, 3.0);

            // Act & Assert - Attempt 1: 0 to 1000 (1000 * 3^0)
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);

            // Act & Assert - Attempt 2: 0 to 3000 (1000 * 3^1)
            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(3_000);

            // Act & Assert - Attempt 3: 0 to 9000 (1000 * 3^2)
            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(9_000);

            // Act & Assert - Attempt 4: 0 to 27000 (1000 * 3^3)
            $delay = $backoff->calculate(4);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(27_000);
        });

        test('calculates exponential jitter backoff with fractional multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000, 1.5);

            // Act & Assert - Attempt 1: 0 to 1000 (1000 * 1.5^0)
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);

            // Act & Assert - Attempt 2: 0 to 1500 (1000 * 1.5^1)
            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_500);

            // Act & Assert - Attempt 3: 0 to 2250 (1000 * 1.5^2)
            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(2_250);

            // Act & Assert - Attempt 4: 0 to 3375 (1000 * 1.5^3)
            $delay = $backoff->calculate(4);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(3_375);
        });

        test('calculates exponential jitter backoff with aggressive multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000, 5.0);

            // Act & Assert - Attempt 1: 0 to 1000 (1000 * 5^0)
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);

            // Act & Assert - Attempt 2: 0 to 5000 (1000 * 5^1)
            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(5_000);

            // Act & Assert - Attempt 3: 0 to 25000 (1000 * 5^2)
            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(25_000);
        });

        test('produces different values on repeated calls due to randomness', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(10_000);

            // Act
            $values = [];

            for ($i = 0; $i < 20; ++$i) {
                $values[] = $backoff->calculate(5);
            }

            // Assert
            $uniqueValues = array_unique($values);
            expect(count($uniqueValues))->toBeGreaterThan(1);
        });

        test('produces statistically distributed jitter values across full range', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(10_000);

            // Act
            $values = [];

            for ($i = 0; $i < 100; ++$i) {
                $values[] = $backoff->calculate(3);
            }

            // Assert - All values should be in valid range
            foreach ($values as $value) {
                expect($value)->toBeGreaterThanOrEqual(0);
                expect($value)->toBeLessThanOrEqual(40_000);
            }

            // Assert - Check distribution across range
            $min = min($values);
            $max = max($values);
            $range = $max - $min;

            expect($range)->toBeGreaterThan(10_000);
            expect(count(array_unique($values)))->toBeGreaterThan(10);
        });

        test('produces zero or near-zero values occasionally with jitter', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000);

            // Act
            $hasLowValue = false;

            for ($i = 0; $i < 200; ++$i) {
                $delay = $backoff->calculate(3);

                if ($delay < 400) {
                    $hasLowValue = true;

                    break;
                }
            }

            // Assert
            expect($hasLowValue)->toBeTrue();
        });

        test('can produce values near maximum exponential bound', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000);

            // Act
            $hasHighValue = false;

            for ($i = 0; $i < 50; ++$i) {
                $delay = $backoff->calculate(3);

                if ($delay > 3_600) {
                    $hasHighValue = true;

                    break;
                }
            }

            // Assert
            expect($hasHighValue)->toBeTrue();
        });

        test('creates from milliseconds with default multiplier', function (): void {
            // Arrange
            $backoff = ExponentialJitterBackoff::milliseconds(100);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(100_000);

            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(200_000);

            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(400_000);
        });

        test('creates from milliseconds with custom multiplier', function (): void {
            // Arrange
            $backoff = ExponentialJitterBackoff::milliseconds(50, 3.0);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(50_000);

            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(150_000);

            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(450_000);
        });

        test('creates from milliseconds with fractional multiplier', function (): void {
            // Arrange
            $backoff = ExponentialJitterBackoff::milliseconds(100, 1.5);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(100_000);

            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(150_000);

            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(225_000);
        });

        test('creates from seconds with default multiplier', function (): void {
            // Arrange
            $backoff = ExponentialJitterBackoff::seconds(1);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000_000);

            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(2_000_000);

            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(4_000_000);
        });

        test('creates from seconds with custom multiplier', function (): void {
            // Arrange
            $backoff = ExponentialJitterBackoff::seconds(1, 2.5);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000_000);

            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(2_500_000);

            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(6_250_000);
        });

        test('creates from seconds with aggressive multiplier', function (): void {
            // Arrange
            $backoff = ExponentialJitterBackoff::seconds(2, 4.0);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(2_000_000);

            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(8_000_000);

            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(32_000_000);
        });
    });

    describe('Sad Paths', function (): void {
        // ExponentialJitterBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles zero base delay', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
            expect($backoff->calculate(10))->toBe(0);
            expect($backoff->calculate(100))->toBe(0);
        });

        test('handles very small base delay', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1);

            // Act & Assert
            $delay = $backoff->calculate(1);
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1);

            $delay = $backoff->calculate(5);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(16);
        });

        test('handles large attempt numbers', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(100);

            // Act & Assert
            $delay = $backoff->calculate(10);
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(0);

            $delay = $backoff->calculate(20);
            expect($delay)->toBeInt();
            expect($delay)->toBeGreaterThanOrEqual(0);
        });

        test('handles multiplier of 1.0', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000, 1.0);

            // Act & Assert - With multiplier of 1.0, all attempts should have same max
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);

            $delay = $backoff->calculate(5);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);

            $delay = $backoff->calculate(10);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(1_000);
        });

        test('handles very small fractional multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(10_000, 1.1);

            // Act & Assert - Attempt 1: 0 to 10_000 (10_000 * 1.1^0)
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(10_000);

            // Act & Assert - Attempt 2: 0 to 11_000 (10_000 * 1.1^1)
            $delay = $backoff->calculate(2);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(11_000);

            // Act & Assert - Attempt 3: 0 to 12_100 (10_000 * 1.1^2)
            $delay = $backoff->calculate(3);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(12_100);
        });

        test('handles first attempt number', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(5_000);

            // Act & Assert - First attempt should use multiplier^0 = 1
            $delay = $backoff->calculate(1);
            expect($delay)->toBeGreaterThanOrEqual(0);
            expect($delay)->toBeLessThanOrEqual(5_000);
        });

        test('ensures jitter always starts from zero for each attempt', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000);

            // Act
            $hasZeroOrVeryLow = false;

            for ($i = 0; $i < 200; ++$i) {
                $delay = $backoff->calculate(2);

                if ($delay <= 100) {
                    $hasZeroOrVeryLow = true;

                    break;
                }
            }

            // Assert
            expect($hasZeroOrVeryLow)->toBeTrue();
        });

        test('maintains independence between sequential calculations', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(1_000);

            // Act
            $delay1 = $backoff->calculate(2);
            $delay2 = $backoff->calculate(3);
            $delay3 = $backoff->calculate(2);

            // Assert
            expect($delay1)->toBeInt();
            expect($delay2)->toBeInt();
            expect($delay3)->toBeInt();

            expect($delay1)->toBeLessThanOrEqual(2_000);
            expect($delay2)->toBeLessThanOrEqual(4_000);
            expect($delay3)->toBeLessThanOrEqual(2_000);
        });

        test('handles consecutive attempts showing exponential growth', function (): void {
            // Arrange
            $backoff = new ExponentialJitterBackoff(100);

            // Act & Assert
            $previousMax = 0;

            for ($attempt = 1; $attempt <= 5; ++$attempt) {
                $expectedMax = 100 * (2 ** ($attempt - 1));

                for ($i = 0; $i < 10; ++$i) {
                    $delay = $backoff->calculate($attempt);
                    expect($delay)->toBeLessThanOrEqual($expectedMax);
                }

                expect($expectedMax)->toBeGreaterThanOrEqual($previousMax);
                $previousMax = $expectedMax;
            }
        });
    });
});
