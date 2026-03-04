<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\PolynomialBackoff;

describe('PolynomialBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('calculates polynomial backoff with default degree (quadratic)', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);   // 1000 * 1^2
            expect($backoff->calculate(2))->toBe(4_000);   // 1000 * 2^2
            expect($backoff->calculate(3))->toBe(9_000);   // 1000 * 3^2
            expect($backoff->calculate(4))->toBe(16_000);  // 1000 * 4^2
            expect($backoff->calculate(5))->toBe(25_000);  // 1000 * 5^2
        });

        test('calculates polynomial backoff with linear degree', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000, 1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);  // 1000 * 1^1
            expect($backoff->calculate(2))->toBe(2_000);  // 1000 * 2^1
            expect($backoff->calculate(3))->toBe(3_000);  // 1000 * 3^1
            expect($backoff->calculate(5))->toBe(5_000);  // 1000 * 5^1
        });

        test('calculates polynomial backoff with cubic degree', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000, 3);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);   // 1000 * 1^3
            expect($backoff->calculate(2))->toBe(8_000);   // 1000 * 2^3
            expect($backoff->calculate(3))->toBe(27_000);  // 1000 * 3^3
            expect($backoff->calculate(4))->toBe(64_000);  // 1000 * 4^3
        });

        test('calculates polynomial backoff with quartic degree', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000, 4);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);    // 1000 * 1^4
            expect($backoff->calculate(2))->toBe(16_000);   // 1000 * 2^4
            expect($backoff->calculate(3))->toBe(81_000);   // 1000 * 3^4
            expect($backoff->calculate(4))->toBe(256_000);  // 1000 * 4^4
        });

        test('calculates polynomial backoff with degree zero (constant)', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000, 0);

            // Act & Assert - x^0 = 1 for any x
            expect($backoff->calculate(1))->toBe(1_000);
            expect($backoff->calculate(2))->toBe(1_000);
            expect($backoff->calculate(5))->toBe(1_000);
            expect($backoff->calculate(10))->toBe(1_000);
        });

        test('creates from milliseconds with default degree', function (): void {
            // Arrange
            $backoff = PolynomialBackoff::milliseconds(100);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);   // 100ms * 1^2
            expect($backoff->calculate(2))->toBe(400_000);   // 100ms * 2^2
            expect($backoff->calculate(3))->toBe(900_000);   // 100ms * 3^2
        });

        test('creates from milliseconds with custom degree', function (): void {
            // Arrange
            $backoff = PolynomialBackoff::milliseconds(50, 3);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(50_000);   // 50ms * 1^3
            expect($backoff->calculate(2))->toBe(400_000);  // 50ms * 2^3
            expect($backoff->calculate(3))->toBe(1_350_000); // 50ms * 3^3
        });

        test('creates from seconds with default degree', function (): void {
            // Arrange
            $backoff = PolynomialBackoff::seconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);   // 1s * 1^2
            expect($backoff->calculate(2))->toBe(4_000_000);   // 1s * 2^2
            expect($backoff->calculate(3))->toBe(9_000_000);   // 1s * 3^2
        });

        test('creates from seconds with custom degree', function (): void {
            // Arrange
            $backoff = PolynomialBackoff::seconds(1, 3);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);   // 1s * 1^3
            expect($backoff->calculate(2))->toBe(8_000_000);   // 1s * 2^3
            expect($backoff->calculate(3))->toBe(27_000_000);  // 1s * 3^3
        });

        test('produces consistent results for same attempt number', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000, 2);

            // Act & Assert
            expect($backoff->calculate(3))->toBe(9_000);
            expect($backoff->calculate(3))->toBe(9_000);
            expect($backoff->calculate(3))->toBe(9_000);
        });
    });

    describe('Sad Paths', function (): void {
        // PolynomialBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles zero base delay', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(0, 2);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
            expect($backoff->calculate(10))->toBe(0);
        });

        test('handles large degree values', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(10, 5);

            // Act & Assert
            $delay = $backoff->calculate(2);  // 10 * 2^5 = 320
            expect($delay)->toBe(320);

            $delay = $backoff->calculate(3);  // 10 * 3^5 = 2430
            expect($delay)->toBe(2_430);
        });

        test('handles negative degree values gracefully', function (): void {
            // Arrange
            $backoff = new PolynomialBackoff(1_000, -1);

            // Act
            $delay1 = $backoff->calculate(1);
            $delay2 = $backoff->calculate(2);

            // Assert - With negative degree, delay decreases
            expect($delay1)->toBeInt();
            expect($delay2)->toBeInt();
            expect($delay2)->toBeLessThan($delay1);
        });
    });
});
