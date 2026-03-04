<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\LinearBackoff;

describe('LinearBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('calculates linear backoff with base microseconds', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);
            expect($backoff->calculate(2))->toBe(2_000);
            expect($backoff->calculate(3))->toBe(3_000);
            expect($backoff->calculate(5))->toBe(5_000);
        });

        test('creates from milliseconds factory method', function (): void {
            // Arrange
            $backoff = LinearBackoff::milliseconds(100);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);
            expect($backoff->calculate(2))->toBe(200_000);
            expect($backoff->calculate(3))->toBe(300_000);
        });

        test('creates from seconds factory method', function (): void {
            // Arrange
            $backoff = LinearBackoff::seconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);
            expect($backoff->calculate(2))->toBe(2_000_000);
            expect($backoff->calculate(3))->toBe(3_000_000);
        });

        test('scales linearly with attempt number', function (): void {
            // Arrange
            $backoff = new LinearBackoff(500);

            // Act & Assert
            expect($backoff->calculate(10))->toBe(5_000);
            expect($backoff->calculate(20))->toBe(10_000);
            expect($backoff->calculate(100))->toBe(50_000);
        });

        test('handles single attempt correctly', function (): void {
            // Arrange
            $backoff = new LinearBackoff(2_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(2_000);
        });

        test('produces same result for same attempt number', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1_500);

            // Act
            $firstCall = $backoff->calculate(5);
            $secondCall = $backoff->calculate(5);

            // Assert
            expect($firstCall)->toBe($secondCall);
            expect($firstCall)->toBe(7_500);
        });

        test('produces monotonically increasing delays', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1_000);

            // Act
            $attempt1 = $backoff->calculate(1);
            $attempt2 = $backoff->calculate(2);
            $attempt3 = $backoff->calculate(3);
            $attempt4 = $backoff->calculate(4);

            // Assert
            expect($attempt2)->toBeGreaterThan($attempt1);
            expect($attempt3)->toBeGreaterThan($attempt2);
            expect($attempt4)->toBeGreaterThan($attempt3);
        });

        test('maintains constant increment between attempts', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1_000);

            // Act
            $increment1to2 = $backoff->calculate(2) - $backoff->calculate(1);
            $increment2to3 = $backoff->calculate(3) - $backoff->calculate(2);
            $increment3to4 = $backoff->calculate(4) - $backoff->calculate(3);

            // Assert
            expect($increment1to2)->toBe(1_000);
            expect($increment2to3)->toBe(1_000);
            expect($increment3to4)->toBe(1_000);
        });

        test('converts milliseconds to microseconds correctly', function (): void {
            // Arrange
            $backoff = LinearBackoff::milliseconds(50);

            // Act & Assert
            // 50ms = 50,000 microseconds
            expect($backoff->calculate(1))->toBe(50_000);
        });

        test('converts seconds to microseconds correctly', function (): void {
            // Arrange
            $backoff = LinearBackoff::seconds(2);

            // Act & Assert
            // 2s = 2,000,000 microseconds
            expect($backoff->calculate(1))->toBe(2_000_000);
        });

        test('milliseconds factory maintains linear scaling', function (): void {
            // Arrange
            $backoff = LinearBackoff::milliseconds(100);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);
            expect($backoff->calculate(2))->toBe(200_000);
            expect($backoff->calculate(3))->toBe(300_000);
            expect($backoff->calculate(10))->toBe(1_000_000);
        });

        test('seconds factory maintains linear scaling', function (): void {
            // Arrange
            $backoff = LinearBackoff::seconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);
            expect($backoff->calculate(2))->toBe(2_000_000);
            expect($backoff->calculate(5))->toBe(5_000_000);
        });
    });

    describe('Sad Paths', function (): void {
        // LinearBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles zero base delay', function (): void {
            // Arrange
            $backoff = new LinearBackoff(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
            expect($backoff->calculate(100))->toBe(0);
        });

        test('handles very small base delays', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1);
            expect($backoff->calculate(10))->toBe(10);
            expect($backoff->calculate(1_000))->toBe(1_000);
        });

        test('handles large base delays', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1_000_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);
            expect($backoff->calculate(10))->toBe(10_000_000);
        });

        test('handles very large attempt numbers', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1_000))->toBe(1_000_000);
            expect($backoff->calculate(10_000))->toBe(10_000_000);
        });

        test('milliseconds factory handles zero value', function (): void {
            // Arrange
            $backoff = LinearBackoff::milliseconds(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
        });

        test('seconds factory handles zero value', function (): void {
            // Arrange
            $backoff = LinearBackoff::seconds(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(5))->toBe(0);
        });

        test('milliseconds factory handles large values', function (): void {
            // Arrange
            $backoff = LinearBackoff::milliseconds(10_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(10_000_000);
            expect($backoff->calculate(2))->toBe(20_000_000);
        });

        test('seconds factory handles large values', function (): void {
            // Arrange
            $backoff = LinearBackoff::seconds(60);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(60_000_000);
            expect($backoff->calculate(2))->toBe(120_000_000);
        });

        test('maintains precision with single microsecond base', function (): void {
            // Arrange
            $backoff = new LinearBackoff(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1);
            expect($backoff->calculate(2))->toBe(2);
            expect($backoff->calculate(3))->toBe(3);
        });

        test('handles fractional millisecond precision via factory', function (): void {
            // Arrange
            $backoff = LinearBackoff::milliseconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);
            expect($backoff->calculate(2))->toBe(2_000);
        });
    });
});
