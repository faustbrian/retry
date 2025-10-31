<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\ExponentialBackoff;

describe('ExponentialBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('calculates exponential backoff with default multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);     // 1000 * 2^0
            expect($backoff->calculate(2))->toBe(2_000);     // 1000 * 2^1
            expect($backoff->calculate(3))->toBe(4_000);     // 1000 * 2^2
            expect($backoff->calculate(4))->toBe(8_000);     // 1000 * 2^3
            expect($backoff->calculate(5))->toBe(16_000);    // 1000 * 2^4
        });

        test('calculates exponential backoff with custom multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000, 3.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);     // 1000 * 3^0
            expect($backoff->calculate(2))->toBe(3_000);     // 1000 * 3^1
            expect($backoff->calculate(3))->toBe(9_000);     // 1000 * 3^2
            expect($backoff->calculate(4))->toBe(27_000);    // 1000 * 3^3
        });

        test('calculates exponential backoff with fractional multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000, 1.5);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);     // 1000 * 1.5^0
            expect($backoff->calculate(2))->toBe(1_500);     // 1000 * 1.5^1
            expect($backoff->calculate(3))->toBe(2_250);     // 1000 * 1.5^2
            expect($backoff->calculate(4))->toBe(3_375);     // 1000 * 1.5^3
        });

        test('creates from milliseconds with default multiplier', function (): void {
            // Arrange
            $backoff = ExponentialBackoff::milliseconds(100);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);      // 100ms * 2^0
            expect($backoff->calculate(2))->toBe(200_000);      // 100ms * 2^1
            expect($backoff->calculate(3))->toBe(400_000);      // 100ms * 2^2
        });

        test('creates from milliseconds with custom multiplier', function (): void {
            // Arrange
            $backoff = ExponentialBackoff::milliseconds(100, 3.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);      // 100ms * 3^0
            expect($backoff->calculate(2))->toBe(300_000);      // 100ms * 3^1
            expect($backoff->calculate(3))->toBe(900_000);      // 100ms * 3^2
        });

        test('creates from seconds with default multiplier', function (): void {
            // Arrange
            $backoff = ExponentialBackoff::seconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);    // 1s * 2^0
            expect($backoff->calculate(2))->toBe(2_000_000);    // 1s * 2^1
            expect($backoff->calculate(3))->toBe(4_000_000);    // 1s * 2^2
        });

        test('creates from seconds with custom multiplier', function (): void {
            // Arrange
            $backoff = ExponentialBackoff::seconds(2, 3.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(2_000_000);    // 2s * 3^0
            expect($backoff->calculate(2))->toBe(6_000_000);    // 2s * 3^1
            expect($backoff->calculate(3))->toBe(18_000_000);   // 2s * 3^2
        });

        test('maintains consistent results on repeated calls', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000);

            // Act
            $firstCall = $backoff->calculate(3);
            $secondCall = $backoff->calculate(3);
            $thirdCall = $backoff->calculate(3);

            // Assert
            expect($firstCall)->toBe(4_000);
            expect($secondCall)->toBe(4_000);
            expect($thirdCall)->toBe(4_000);
        });

        test('handles milliseconds conversion correctly', function (): void {
            // Arrange
            $backoffDirect = new ExponentialBackoff(250_000, 2.0);
            $backoffFactory = ExponentialBackoff::milliseconds(250, 2.0);

            // Act & Assert
            expect($backoffFactory->calculate(1))->toBe($backoffDirect->calculate(1));
            expect($backoffFactory->calculate(5))->toBe($backoffDirect->calculate(5));
        });

        test('handles seconds conversion correctly', function (): void {
            // Arrange
            $backoffDirect = new ExponentialBackoff(3_000_000, 2.0);
            $backoffFactory = ExponentialBackoff::seconds(3, 2.0);

            // Act & Assert
            expect($backoffFactory->calculate(1))->toBe($backoffDirect->calculate(1));
            expect($backoffFactory->calculate(5))->toBe($backoffDirect->calculate(5));
        });

        test('produces predictable results for identical configurations', function (): void {
            // Arrange
            $backoff1 = new ExponentialBackoff(1_000, 2.0);
            $backoff2 = new ExponentialBackoff(1_000, 2.0);

            // Act & Assert
            for ($i = 1; $i <= 10; ++$i) {
                expect($backoff1->calculate($i))->toBe($backoff2->calculate($i));
            }
        });

        test('demonstrates exponential growth rate', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(100, 2.0);

            // Act & Assert
            $previous = $backoff->calculate(1);

            for ($i = 2; $i <= 5; ++$i) {
                $current = $backoff->calculate($i);
                // Each delay should be exactly 2x the previous
                expect($current)->toBe($previous * 2);
                $previous = $current;
            }
        });
    });

    describe('Sad Paths', function (): void {
        // ExponentialBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles multiplier of 1.0 resulting in constant delay', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000, 1.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);
            expect($backoff->calculate(2))->toBe(1_000);
            expect($backoff->calculate(5))->toBe(1_000);
            expect($backoff->calculate(10))->toBe(1_000);
        });

        test('handles zero base delay', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(2))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
        });

        test('handles very small base delay', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1);
            expect($backoff->calculate(2))->toBe(2);
            expect($backoff->calculate(3))->toBe(4);
            expect($backoff->calculate(10))->toBe(512);
        });

        test('handles large attempt numbers', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(10))->toBe(512_000);     // 1000 * 2^9
            expect($backoff->calculate(15))->toBe(16_384_000);  // 1000 * 2^14
            expect($backoff->calculate(20))->toBe(524_288_000); // 1000 * 2^19
        });

        test('handles very small multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(10_000, 1.1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(10_000);       // 10000 * 1.1^0
            expect($backoff->calculate(2))->toBe(11_000);       // 10000 * 1.1^1
            expect($backoff->calculate(3))->toBe(12_100);       // 10000 * 1.1^2
        });

        test('handles large multiplier', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(100, 10.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100);          // 100 * 10^0
            expect($backoff->calculate(2))->toBe(1_000);        // 100 * 10^1
            expect($backoff->calculate(3))->toBe(10_000);       // 100 * 10^2
            expect($backoff->calculate(4))->toBe(100_000);      // 100 * 10^3
        });

        test('truncates fractional results to integer', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000, 1.7);

            // Act & Assert
            // 1000 * 1.7^2 = 2889 (after int cast)
            expect($backoff->calculate(3))->toBe(2_889);
            // 1000 * 1.7^3 = 4912 (after int cast)
            expect($backoff->calculate(4))->toBe(4_912);
        });

        test('handles very large base values', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);
            expect($backoff->calculate(2))->toBe(2_000_000);
            expect($backoff->calculate(3))->toBe(4_000_000);
        });

        test('calculates correctly for sequential attempts', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(500, 2.0);

            // Act
            $delays = [];

            for ($i = 1; $i <= 8; ++$i) {
                $delays[$i] = $backoff->calculate($i);
            }

            // Assert
            expect($delays)->toBe([
                1 => 500,
                2 => 1_000,
                3 => 2_000,
                4 => 4_000,
                5 => 8_000,
                6 => 16_000,
                7 => 32_000,
                8 => 64_000,
            ]);
        });

        test('maintains precision with milliseconds factory', function (): void {
            // Arrange
            $backoff = ExponentialBackoff::milliseconds(1, 2.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);
            expect($backoff->calculate(2))->toBe(2_000);
            expect($backoff->calculate(10))->toBe(512_000);
        });

        test('maintains precision with seconds factory', function (): void {
            // Arrange
            $backoff = ExponentialBackoff::seconds(1, 2.0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);
            expect($backoff->calculate(2))->toBe(2_000_000);
            expect($backoff->calculate(10))->toBe(512_000_000);
        });

        test('handles different multipliers with same base', function (): void {
            // Arrange
            $backoff2x = new ExponentialBackoff(1_000, 2.0);
            $backoff3x = new ExponentialBackoff(1_000, 3.0);
            $backoff4x = new ExponentialBackoff(1_000, 4.0);

            // Act & Assert
            expect($backoff2x->calculate(5))->toBe(16_000);
            expect($backoff3x->calculate(5))->toBe(81_000);
            expect($backoff4x->calculate(5))->toBe(256_000);
        });

        test('handles multiplier less than 1 for decreasing backoff', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(10_000, 0.5);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(10_000);       // 10000 * 0.5^0
            expect($backoff->calculate(2))->toBe(5_000);        // 10000 * 0.5^1
            expect($backoff->calculate(3))->toBe(2_500);        // 10000 * 0.5^2
            expect($backoff->calculate(4))->toBe(1_250);        // 10000 * 0.5^3
        });

        test('handles attempt number 1 correctly as base case', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(5_000, 2.5);

            // Act & Assert
            // Attempt 1 should always return base value (multiplier^0 = 1)
            expect($backoff->calculate(1))->toBe(5_000);
        });

        test('handles non-sequential attempt calls', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(5))->toBe(16_000);
            expect($backoff->calculate(2))->toBe(2_000);
            expect($backoff->calculate(8))->toBe(128_000);
            expect($backoff->calculate(1))->toBe(1_000);
        });

        test('handles various real-world multipliers', function (): void {
            // Arrange
            $backoff1_5x = new ExponentialBackoff(1_000, 1.5);
            $backoff2x = new ExponentialBackoff(1_000, 2.0);
            $backoff2_5x = new ExponentialBackoff(1_000, 2.5);

            // Act & Assert
            // Compare growth at attempt 4
            expect($backoff1_5x->calculate(4))->toBe(3_375);
            expect($backoff2x->calculate(4))->toBe(8_000);
            expect($backoff2_5x->calculate(4))->toBe(15_625);
        });

        test('handles edge case with multiplier approaching zero', function (): void {
            // Arrange
            $backoff = new ExponentialBackoff(10_000, 0.1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(10_000);       // 10000 * 0.1^0
            expect($backoff->calculate(2))->toBe(1_000);        // 10000 * 0.1^1
            expect($backoff->calculate(3))->toBe(100);          // 10000 * 0.1^2
            expect($backoff->calculate(4))->toBe(10);           // 10000 * 0.1^3
        });
    });
});
