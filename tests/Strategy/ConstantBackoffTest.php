<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Retry\Strategy\ConstantBackoff;

describe('ConstantBackoff', function (): void {
    describe('Happy Paths', function (): void {
        test('returns constant delay for all attempts', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(1_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);
            expect($backoff->calculate(2))->toBe(1_000);
            expect($backoff->calculate(5))->toBe(1_000);
            expect($backoff->calculate(10))->toBe(1_000);
            expect($backoff->calculate(100))->toBe(1_000);
        });

        test('returns constant delay starting from first attempt', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(5_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(5_000);
        });

        test('handles typical delay values', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(50_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(50_000);
            expect($backoff->calculate(10))->toBe(50_000);
        });

        test('creates from milliseconds with correct conversion', function (): void {
            // Arrange
            $backoff = ConstantBackoff::milliseconds(100);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(100_000);
            expect($backoff->calculate(5))->toBe(100_000);
        });

        test('creates from seconds with correct conversion', function (): void {
            // Arrange
            $backoff = ConstantBackoff::seconds(2);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(2_000_000);
            expect($backoff->calculate(5))->toBe(2_000_000);
        });

        test('creates from single millisecond', function (): void {
            // Arrange
            $backoff = ConstantBackoff::milliseconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000);
        });

        test('creates from single second', function (): void {
            // Arrange
            $backoff = ConstantBackoff::seconds(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1_000_000);
        });

        test('maintains consistency across multiple calculations', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(10_000);

            // Act
            $firstCall = $backoff->calculate(5);
            $secondCall = $backoff->calculate(5);
            $thirdCall = $backoff->calculate(5);

            // Assert
            expect($firstCall)->toBe($secondCall);
            expect($secondCall)->toBe($thirdCall);
            expect($firstCall)->toBe(10_000);
        });

        test('produces identical results for same delay configuration', function (): void {
            // Arrange
            $backoff1 = new ConstantBackoff(3_000);
            $backoff2 = new ConstantBackoff(3_000);

            // Act & Assert
            expect($backoff1->calculate(1))->toBe($backoff2->calculate(1));
            expect($backoff1->calculate(10))->toBe($backoff2->calculate(10));
        });
    });

    describe('Sad Paths', function (): void {
        // ConstantBackoff has no error conditions - all inputs are valid
    });

    describe('Edge Cases', function (): void {
        test('handles zero delay', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
            expect($backoff->calculate(10))->toBe(0);
        });

        test('handles zero milliseconds factory', function (): void {
            // Arrange
            $backoff = ConstantBackoff::milliseconds(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
        });

        test('handles zero seconds factory', function (): void {
            // Arrange
            $backoff = ConstantBackoff::seconds(0);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(0);
        });

        test('handles very large delay values', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(999_999_999);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(999_999_999);
            expect($backoff->calculate(100))->toBe(999_999_999);
        });

        test('returns same delay regardless of attempt number', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(2_500);

            // Act
            $results = [];

            for ($i = 1; $i <= 50; ++$i) {
                $results[] = $backoff->calculate($i);
            }

            // Assert
            expect($results)->each->toBe(2_500);
        });

        test('handles minimum positive microsecond value', function (): void {
            // Arrange
            $backoff = new ConstantBackoff(1);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(1);
        });

        test('creates from large millisecond values', function (): void {
            // Arrange
            $backoff = ConstantBackoff::milliseconds(5_000);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(5_000_000);
        });

        test('creates from large second values', function (): void {
            // Arrange
            $backoff = ConstantBackoff::seconds(60);

            // Act & Assert
            expect($backoff->calculate(1))->toBe(60_000_000);
        });
    });
});
